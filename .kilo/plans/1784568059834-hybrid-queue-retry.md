# Hybrid Queue-Based Retry for Transient Order Failures

## Goal

When a transient DB failure (deadlock, serialization failure, lock timeout) occurs during
synchronous order processing, persist a `pending` order and dispatch a queue job that
automatically retries with exponential backoff. Permanent failures (insufficient balance,
stock gone, duplicate purchase) still fail fast with no persisted order.

## Architecture Decision: Hybrid

- **Happy path:** synchronous `DB::transaction()` → returns `200` with completed order (unchanged).
- **Transient failure:** transaction rolls back (no order) → create `pending` order → dispatch
  `ProcessOrderPaymentJob` → return `202` with pending order.
- **Permanent failure:** transaction rolls back (no order) → re-throw domain exception → `4xx`.
- Client polls `GET /api/orders/{id}` for the final state (already exists).

## Transient vs Permanent Failure Classification

| Type | Exceptions | Action |
|------|-----------|--------|
| **Transient** (retry) | `QueryException` SQLSTATE `40001` (serialization), `40P01` (deadlock), `55P03` (lock timeout) | Create pending order, dispatch job |
| **Permanent** (no retry) | `InsufficientBalanceException`, `InsufficientStockException`, `DuplicatePurchaseException`, `ProductInactiveException`, `FlashSaleNotActiveException`, `MaximumQuantityExceededException`, `WalletNotFoundException`, all other exceptions | Re-throw immediately, no order persisted |

## Implementation Steps

### 1. Queue infrastructure (migration)

- Run `php artisan make:queue-table` to generate the migration for `jobs` + `failed_jobs` tables.
- `phpunit.xml`: keep `QUEUE_CONNECTION=sync` for existing tests (jobs run inline). New
  retry-specific tests will use `Queue::fake()` / direct `handle()` calls.
- `.env.example`: ensure `QUEUE_CONNECTION=database` is documented.

### 2. New Job: `app/Jobs/ProcessOrderPaymentJob.php`

Modeled on existing `SendNotificationJob` (same `$tries`/`$backoff`/`$timeout` pattern).

```
class ProcessOrderPaymentJob implements ShouldQueue
    use Queueable

    public $tries = 5
    public $timeout = 60
    public $backoff = [5, 10, 30, 60]

    __construct(public readonly Order $order)

    handle():
        # Idempotent guard: skip if already completed
        if $order->fresh()->status === Completed:
            return

        try:
            $this->orderService->processOrder($this->order)
        catch (permanent exceptions listed above):
            $this->orderService->failOrder($this->order, $e)
            return          # do NOT re-throw → no retry
        # transient QueryException propagates → Laravel auto-retries per $tries/$backoff

    failed(Throwable $e):
        # Called after all retries exhausted
        $this->orderService->failOrder($this->order, $e)
```

**Permanent exception detection in `handle()`:** catch `\Throwable`, check
`$this->isTransient($e)`. If transient → re-throw. If permanent → mark failed, return.

### 3. Refactor `app/Services/OrderService.php`

Extract the core payment logic so both the sync path and the job can reuse it.

#### 3a. New private method: `executePayment()` — transaction-free core logic

Extracted from the current `placeOrder()` transaction body (lines 62–136). Takes an
existing `Order` and does the locked work:

```
private function executePayment(
    Order $order, int $productId, int $quantity,
    ?Coupon $coupon, float $totalAmount, ?int $merchantId
): void
    # Lock product for update
    $lockedProduct = findProductByIdForUpdate($productId)
    if null → throw Exception('Product not found.')

    # Re-check stock under lock
    if $lockedProduct->available_stock < $quantity → throw InsufficientStockException

    # Duplicate-purchase guard (EXCLUDE current order)
    if hasActivePurchaseForProduct($user->id, $productId, $order->id):
        throw DuplicatePurchaseException

    # Create pending payment transaction
    $paymentTransaction = paymentTransactionService->createPending($order, ...)

    # Wallet transfer (double-entry ledger)
    walletService->transfer($paymentTransaction->customerWallet, ...)

    # Decrement stock
    orderRepository->decrementStock($lockedProduct, $quantity)

    # Increment coupon usage
    if $coupon → couponService->incrementUsage($coupon->id)

    # Mark payment success
    paymentTransactionService->markSuccess($paymentTransaction)

    # Finalize order
    orderRepository->updateOrderStatus($order, Completed, Paid)
```

**Note:** `executePayment()` does NOT open `DB::transaction()` — the caller owns the
transaction. This preserves the existing architecture rule (only OrderService owns
transactions; called services are transaction-free).

#### 3b. New public method: `processOrder(Order $order): Order` — used by the job

```
public function processOrder(Order $order): Order
    # Idempotent: already done
    if $order->status === Completed → return $order

    # Read order data (source of truth)
    $item = $order->orderItems->first()
    $quantity = $item->quantity
    $coupon = $order->coupon_id ? Coupon::find($order->coupon_id) : null
    $totalAmount = (float) $order->total
    $productId = $order->product_id
    $merchantId = Product::find($productId)->merchant_id

    DB::transaction(fn() =>
        $this->executePayment($order, $productId, $quantity, $coupon, $totalAmount, $merchantId)
    )

    DB::afterCommit(fn() => event(new OrderPlaced($order)))

    return $order->fresh()->load(...)
```

#### 3c. Refactor `placeOrder()` — sync path with transient-failure fallback

```
public function placeOrder(User $user, int $productId, int $quantity, ?string $couponCode): Order
    # Read-only validation (unchanged)
    $product = findProduct($productId)
    validateProduct($product)
    validateQuantity($product, $quantity)
    [$coupon, $subtotal, $discount, $totalAmount] = resolvePricing(...)

    try:
        $order = DB::transaction(function() use (...) {
            $order = createOrder(pending)
            createOrderItem(...)
            $this->executePayment($order, $product->id, $quantity, $coupon, $totalAmount, $product->merchant_id)
            return $order
        })
    catch (QueryException $e):
        if !$this->isTransient($e):
            throw $e
        # Transient: create pending order for queue retry
        return $this->dispatchRetry(
            $user, $product->id, $quantity, $coupon,
            $subtotal, $discount, $totalAmount, $product->merchant_id
        )

    DB::afterCommit(fn() => event(new OrderPlaced($order)))
    return $order->fresh()->load(...)
```

#### 3d. New private method: `dispatchRetry()` — creates pending order + dispatches job

Called only on transient failure. Runs outside the rolled-back transaction.

```
private function dispatchRetry(...): Order
    $order = DB::transaction(function() {
        $order = createOrder(status=pending, payment_status=pending, ...)
        createOrderItem(...)
        return $order
    })

    ProcessOrderPaymentJob::dispatch($order)

    return $order->fresh()->load(...)
```

#### 3e. New public method: `failOrder(Order $order, Throwable $e): void`

Called by the job on permanent failure or after retries exhausted.

```
public function failOrder(Order $order, Throwable $e): void
    DB::transaction(function() use ($order, $e) {
        # Mark payment failed (if a pending payment exists)
        $payment = $order->paymentTransaction
        if $payment && $payment->status === Pending:
            paymentTransactionService->markFailed($payment)

        # Mark order failed
        orderRepository->updateOrderStatus($order, Failed, PaymentStatus::Failed)
    })

    # Release idempotency key so client can retry
    IdempotencyKey::where('order_id', $order->id)->delete()

    Log::error('Order processing failed permanently', [...])
```

#### 3f. New private method: `isTransient(Throwable $e): bool`

```
private function isTransient(Throwable $e): bool
    if !$e instanceof QueryException:
        return false

    $sqlState = $e->getCode()   # or $e->errorInfo[0] for PDO SQLSTATE
    return in_array($sqlState, ['40001', '40P01', '55P03'], true)
```

### 4. Modify `OrderRepository` + `OrderRepositoryInterface`

Add `$excludeOrderId` parameter to duplicate-purchase guard:

```
hasActivePurchaseForProduct(int $userId, int $productId, ?int $excludeOrderId = null): bool
    ->whereNotIn('status', [Cancelled, Failed])
    ->when($excludeOrderId, fn($q) => $q->where('id', '!=', $excludeOrderId))
    ->exists()
```

### 5. Modify `app/Http/Controllers/Customer/OrderController.php` — store()

After `placeOrder()` returns, check status for HTTP code:

```
[$order, $replayed] = $this->idempotencyService->execute(...)

$status = $order->status === OrderStatus::Completed ? 200 : 202
$message = $order->status === OrderStatus::Completed
    ? 'Order placed successfully.'
    : 'Order is being processed.'

return $this->resourceResponse(new OrderResource($order), $message, $status)
```

Note: `resourceResponse()` must accept a status code parameter (check current signature).

### 6. Idempotency interaction (no IdempotencyService change needed)

The existing `IdempotencyService::execute()` already handles all cases correctly:

| Scenario | IdempotencyService behavior |
|----------|---------------------------|
| Sync success | Key linked to completed order → 200 |
| Permanent failure | Order never persists → exception → key deleted → 4xx |
| Transient failure | `dispatchRetry()` creates pending order → `placeOrder()` returns it → IdempotencyService links key → 202 |
| Client retries during pending | Key exists, order_id set → returns pending order ("being processed") |
| Job succeeds | Order becomes completed → client polls → sees completed |
| Job fails permanently | `failOrder()` deletes key → client can retry with same key |

### 7. Stock during retry window

Stock is **NOT** reserved during the retry window. When the job runs, it re-checks stock
under `lockForUpdate()`. If stock is gone, the order fails permanently. This is correct for
flash-sale semantics (first-come-first-serve).

## Files Changed

| File | Change |
|------|--------|
| `app/Jobs/ProcessOrderPaymentJob.php` | **NEW** — queue job with retry config |
| `app/Services/OrderService.php` | **MAJOR** — extract `executePayment`, add `processOrder`/`dispatchRetry`/`failOrder`/`isTransient` |
| `app/Repositories/OrderRepository.php` | Add `$excludeOrderId` to `hasActivePurchaseForProduct` |
| `app/Interfaces/OrderRepositoryInterface.php` | Update signature |
| `app/Http/Controllers/Customer/OrderController.php` | 200 vs 202 based on order status |
| `app/Traits/ApiResponseTrait.php` | Ensure `resourceResponse()` accepts status code |
| `database/migrations/*_create_jobs_table.php` | **NEW** — `php artisan make:queue-table` |
| `tests/Feature/OrderQueueRetryTest.php` | **NEW** — test job processing, retry, permanent failure |

## Tests

### New: `tests/Feature/OrderQueueRetryTest.php`

1. **`test_job_processes_pending_order_successfully`** — create pending order manually,
   dispatch job, assert order becomes completed + payment success + stock decremented.
2. **`test_job_marks_order_failed_on_permanent_exception`** — mock/force insufficient
   balance, dispatch job, assert order → failed, payment → failed, idempotency key released.
3. **`test_job_is_idempotent_on_already_completed_order`** — dispatch job on completed
   order, assert no double-processing.
4. **`test_place_order_returns_202_on_transient_failure`** — mock `DB::transaction` to
   throw `QueryException(40001)`, assert 202 + pending order + job dispatched.
5. **`test_transient_failure_then_job_success_completes_order`** — end-to-end: transient
   failure → pending order → job processes → completed.
6. **`test_idempotency_replay_during_pending_returns_pending_order`** — replay same key
   while job pending → 202 "being processed", not a new order.

### Existing tests

All 62 existing tests must still pass. Key concern: tests that assert
`assertDatabaseCount('orders', 0)` on permanent failures still hold (permanent failures
don't persist orders). The notification test (exactly 1 notification row) must still pass
since `OrderPlaced` is dispatched via `afterCommit` in both paths.

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Job runs before `dispatchRetry()` transaction commits | Set `after_commit => true` on the `database` queue connection, or dispatch inside `DB::afterCommit()` |
| Double notification (OrderPlaced fired twice if sync fails afterCommit then job fires it) | `OrderPlaced` is only dispatched on the SUCCESS path, never in `dispatchRetry()`. The job dispatches it after its own success. So at most once. |
| Client sees stale pending order via idempotency replay | Acceptable — response says "being processed". Client polls for final state. |
| `executePayment()` called inside nested `DB::transaction()` (sync path) | It is transaction-free (no `DB::transaction` call). The outer transaction in `placeOrder()` is the only one. The job's `processOrder()` opens its own. No nesting. |
| Coupon TOCTOU still open (separate issue) | Out of scope for this plan. The job calls `incrementUsage` inside the transaction (same as now). |

## Out of Scope

- Coupon usage-limit TOCTOU fix (separate).
- Refund deadlock window (separate; this plan reduces purchase-path deadlock impact).
- Webhook/callback for async completion (polling via existing `GET /orders/{id}` is sufficient).
- Stock reservation during retry window (intentionally not reserved — fair flash-sale semantics).
