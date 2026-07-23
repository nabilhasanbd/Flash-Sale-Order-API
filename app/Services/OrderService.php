<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Events\OrderPlaced;
use App\Exceptions\DuplicatePurchaseException;
use App\Exceptions\FlashSaleNotActiveException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MaximumQuantityExceededException;
use App\Exceptions\ProductInactiveException;
use App\Interfaces\OrderRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Jobs\ProcessOrderPaymentJob;
use App\Models\Coupon;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * PostgreSQL SQLSTATE codes that indicate a transient failure worth retrying.
     */
    private const TRANSIENT_SQL_STATES = ['40001', '40P01', '55P03'];

    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected ProductRepositoryInterface $productRepository,
        protected CouponService $couponService,
        protected PaymentTransactionService $paymentTransactionService,
        protected WalletService $walletService,
    ) {}

    /**
     * Synchronous order placement with queue-based retry fallback.
     *
     * Attempts the full purchase synchronously. On success, returns a completed
     * order. On transient failure (deadlock/serialization), creates a pending
     * order and dispatches a queue job for automatic retry. On permanent
     * failure, re-throws the domain exception.
     */
    public function placeOrder(
        User $user,
        int $productId,
        int $quantity,
        ?string $couponCode = null
    ): Order {
        // ---- Read-only validation (no locks, no transaction yet) ----
        $product = $this->productRepository->findProduct($productId);

        if ($product === null) {
            throw new \Exception('Product not found.');
        }

        $this->validateProduct($product);

        $this->validateQuantity($product, $quantity);

        [$coupon, $subtotal, $discount, $totalAmount] = $this->resolvePricing($product, $quantity, $couponCode);

        // ---- Critical section: OrderService is the sole owner of the DB transaction ----
        try {
            $order = DB::transaction(function () use (
                $user,
                $product,
                $quantity,
                $coupon,
                $subtotal,
                $discount,
                $totalAmount
            ) {
                $order = $this->orderRepository->createOrder([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'coupon_id' => $coupon?->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $totalAmount,
                    'payment_status' => PaymentStatus::Pending,
                    'status' => OrderStatus::Pending,
                ]);

                $this->orderRepository->createOrderItem([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $product->price,
                    'subtotal' => $subtotal,
                ]);

                $this->executePayment($order, $user, $quantity);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'orders_user_product_active_unique')) {
                throw new DuplicatePurchaseException;
            }

            throw $e;
        } catch (QueryException $e) {
            if (! $this->isTransient($e)) {
                throw $e;
            }

            return $this->dispatchRetry(
                $user,
                $product,
                $quantity,
                $coupon,
                $subtotal,
                $discount,
                $totalAmount
            );
        }

        // Dispatch only after the transaction has committed.
        DB::afterCommit(function () use ($order) {
            event(new OrderPlaced($order));
        });

        return $order->fresh()->load([
            'orderItems.product',
            'paymentTransaction.walletTransactions',
        ]);
    }

    /**
     * Process a pending order's payment. Used by the queue job.
     *
     * Opens its own transaction, locks the product, performs the wallet
     * transfer, decrements stock, and finalises the order. Idempotent: if the
     * order is already completed, returns immediately.
     */
    public function processOrder(Order $order): Order
    {
        $order = $order->fresh();

        if ($order->status === OrderStatus::Completed) {
            return $order;
        }

        $user = $order->user;
        $item = $order->orderItems->first();
        $quantity = $item !== null ? $item->quantity : 1;

        DB::transaction(fn () => $this->executePayment($order, $user, $quantity));

        DB::afterCommit(function () use ($order) {
            event(new OrderPlaced($order));
        });

        return $order->fresh()->load([
            'orderItems.product',
            'paymentTransaction.walletTransactions',
        ]);
    }

    /**
     * Mark an order as permanently failed and release its idempotency key.
     *
     * Called by the queue job when a permanent exception occurs or when all
     * retry attempts are exhausted.
     */
    public function failOrder(Order $order, \Throwable $e): void
    {
        DB::transaction(function () use ($order) {
            $payment = $order->paymentTransaction()
                ->where('status', PaymentTransactionStatus::Pending->value)
                ->first();

            if ($payment !== null) {
                $this->paymentTransactionService->markFailed($payment);
            }

            $this->orderRepository->updateOrderStatus(
                $order,
                OrderStatus::Failed,
                PaymentStatus::Failed
            );
        });

        IdempotencyKey::where('order_id', $order->id)->delete();

        Log::error('Order processing failed permanently', [
            'order_id' => $order->id,
            'error' => $e->getMessage(),
            'exception_class' => $e::class,
        ]);
    }

    /**
     * Determine whether a database exception is transient (worth retrying).
     *
     * Transient failures include deadlocks (40P01), serialization conflicts
     * (40001), and lock timeouts (55P03).
     */
    public function isTransient(\Throwable $e): bool
    {
        if (! ($e instanceof QueryException)) {
            return false;
        }

        $previous = $e->getPrevious();

        if ($previous instanceof \PDOException && isset($previous->errorInfo[0])) {
            return in_array($previous->errorInfo[0], self::TRANSIENT_SQL_STATES, true);
        }

        $message = $e->getMessage();

        foreach (self::TRANSIENT_SQL_STATES as $state) {
            if (str_contains($message, $state)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a pending order for queue-based retry after a transient failure.
     *
     * Runs outside the rolled-back sync transaction. The order is committed in
     * a pending state, and a ProcessOrderPaymentJob is dispatched via
     * afterCommit so it only runs once the order is visible in the database.
     *
    protected function dispatchRetry(
        User $user,
        Product $product,
        int $quantity,
        ?Coupon $coupon,
        float $subtotal,
        float $discount,
        float $totalAmount
    ): Order {
        $order = DB::transaction(function () use (
            $user,
            $product,
            $quantity,
            $coupon,
            $subtotal,
            $discount,
            $totalAmount
        ) {
            $order = $this->orderRepository->createOrder([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'coupon_id' => $coupon?->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $totalAmount,
                'payment_status' => PaymentStatus::Pending,
                'status' => OrderStatus::Pending,
            ]);

            $this->orderRepository->createOrderItem([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => (float) $product->price,
                'subtotal' => $subtotal,
            ]);

            DB::afterCommit(fn () => ProcessOrderPaymentJob::dispatch($order));

            return $order;
        });

        return $order->fresh()->load([
            'orderItems.product',
        ]);
    }

    /**
     * Execute the core payment logic for an existing order.
     *
     * Transaction-free: the caller owns the database transaction. This method
     * locks the product, re-checks stock, guards against duplicates, creates
     * the payment transaction, transfers funds, decrements stock, increments
     * coupon usage, and finalises the order.
     */
    private function executePayment(Order $order, User $user, int $quantity): void
    {
        // Lock product for update and reload it to read the live stock.
        $lockedProduct = $this->productRepository->findProductByIdForUpdate($order->product_id);

        if ($lockedProduct === null) {
            throw new \Exception('Product not found.');
        }

        // Re-check stock under the lock to prevent overselling.
        if ((int) $lockedProduct->available_stock < $quantity) {
            throw new InsufficientStockException;
        }

        // Duplicate-purchase guard: exclude the current order so the retry job
        // does not trip over its own pending order.
        if ($this->orderRepository->hasActivePurchaseForProduct(
            $user->id,
            $lockedProduct->id,
            $order->id
        )) {
            throw new DuplicatePurchaseException;
        }

        // Create the PaymentTransaction (status = pending) bound to the wallets.
        $paymentTransaction = $this->paymentTransactionService->createPending(
            $order,
            $user,
            (float) $order->total,
            $lockedProduct->merchant_id
        );

        // Move funds: customer debit -> merchant credit, with a double-entry ledger.
        $this->walletService->transfer(
            $paymentTransaction->customerWallet,
            $paymentTransaction->merchantWallet,
            (float) $order->total,
            $paymentTransaction,
            'Flash Sale Purchase - Order #'.$order->id
        );

        // Reduce product stock.
        $this->orderRepository->decrementStock($lockedProduct, $quantity);

        // Increase coupon usage.
        if ($order->coupon_id !== null) {
            $this->couponService->incrementUsage($order->coupon_id);
        }

        // Mark the payment successful.
        $this->paymentTransactionService->markSuccess($paymentTransaction);

        // Finalise the order.
        $this->orderRepository->updateOrderStatus(
            $order,
            OrderStatus::Completed,
            PaymentStatus::Paid
        );
    }

    protected function validateProduct(Product $product): void
    {
        if (! $product->isActive()) {
            throw new ProductInactiveException;
        }

        if (! $product->isFlashSaleRunning()) {
            throw new FlashSaleNotActiveException;
        }
    }

    protected function validateQuantity(Product $product, int $quantity): void
    {
        $maxQuantity = $product->flash_sale_max_quantity_per_order ?? 3;

        if ($quantity > $maxQuantity) {
            throw new MaximumQuantityExceededException($maxQuantity, $quantity);
        }

        if ((int) $product->available_stock < $quantity) {
            throw new InsufficientStockException;
        }
    }

    /**
     * Validate the coupon (if any) and calculate the final amount.
     *
     * @return array{0: ?Coupon, 1: float, 2: float, 3: float}
     */
    protected function resolvePricing(Product $product, int $quantity, ?string $couponCode): array
    {
        $subtotal = (float) $product->price * $quantity;
        $discount = 0.0;
        $coupon = null;

        if ($couponCode !== null) {
            $coupon = $this->couponService->validateCoupon($couponCode, $subtotal);
            $discountData = $this->couponService->calculateDiscount($coupon, $subtotal);
            $discount = (float) $discountData['discount'];
        }

        return [$coupon, $subtotal, $discount, $subtotal - $discount];
    }
}
