# Missing Items & Solutions

## Critical Missing Components

### 1. Order Routes (HIGH PRIORITY)

**File:** `routes/api.php`

**Add these routes:**
```php
// Customer order routes (inside auth:sanctum middleware)
Route::prefix('orders')->group(function () {
    Route::post('/', [App\Http\Controllers\Customer\OrderController::class, 'store']);
    Route::get('/', [App\Http\Controllers\Customer\OrderController::class, 'index']);
    Route::get('/{order}', [App\Http\Controllers\Customer\OrderController::class, 'show']);
});

// Admin order routes
Route::prefix('admin/orders')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\OrderController::class, 'index']);
    Route::get('/{order}', [App\Http\Controllers\Admin\OrderController::class, 'show']);
});
```

### 2. Customer Order Controller

**File:** `app/Http/Controllers/Customer/OrderController.php`

```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder(
            user: $request->user(),
            productId: $request->product_id,
            quantity: $request->quantity,
            couponCode: $request->coupon_code,
        );

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($order);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }
}
```

### 3. Admin Order Controller

**File:** `app/Http/Controllers/Admin/OrderController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'product', 'orderItems.product']);

        // Filters
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('product_id')) {
            $query->whereHas('orderItems', function (Builder $q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }

        if ($request->has('customer_id')) {
            $query->where('user_id', $request->customer_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'coupon', 'orderItems.product', 'walletTransaction']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }
}
```

### 4. Complete StoreOrderRequest

**File:** `app/Http/Requests/StoreOrderRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:3'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'Product not found.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Minimum quantity is 1.',
            'quantity.max' => 'Maximum quantity per order is 3.',
            'coupon_code.exists' => 'Invalid coupon code.',
        ];
    }
}
```

### 5. Order API Resources

**File:** `app/Http/Resources/OrderResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] ?? null,
            'product' => [
                'id' => $this->orderItems->first()?->product->id,
                'name' => $this->orderItems->first()?->product->name,
                'price' => $this->orderItems->first()?->unit_price,
            ] ?? null,
            'quantity' => $this->orderItems->first()?->quantity ?? 0,
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'payment_status' => $this->payment_status->value,
            'status' => $this->status->value,
            'coupon' => [
                'code' => $this->coupon->code ?? null,
                'discount_applied' => (string) $this->discount,
            ] ?? null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

## Additional Missing Items

### 6. Add Quantity Validation to OrderService

**File:** `app/Services/OrderService.php`

**Add to line 58-62:**
```php
if ($availableStock < $quantity) {
    throw new InsufficientStockException();
}

// Add this validation
if ($quantity > 3) {
    throw new \Exception('Maximum quantity per order is 3.');
}
```

### 7. Add Flash Sale Timing Validation

**File:** `app/Services/OrderService.php`

**Add after line 57:**
```php
// Validate flash sale timing
if (!$product->isFlashSaleRunning()) {
    throw new \Exception('Flash sale is not currently running.');
}
```

## Testing Implementation

### 8. Order Feature Tests

**File:** `tests/Feature/OrderTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Enums\UserRole;
use App\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->token = $this->customer->createToken('auth_token')->plainTextToken;

        $this->product = Product::factory()->create([
            'status' => ProductStatus::Active,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'available_stock' => 10,
        ]);

        $wallet = Wallet::factory()->create([
            'user_id' => $this->customer->id,
            'balance' => 1000.00,
        ]);
    }

    public function test_customer_can_place_order(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order placed successfully.',
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'total' => 1800.00, // 2 * 900
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'available_stock' => 8, // 10 - 2
        ]);
    }

    public function test_order_fails_with_insufficient_stock(): void
    {
        $this->product->update(['available_stock' => 1]);

        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(400);
    }

    public function test_order_fails_with_insufficient_wallet_balance(): void
    {
        $this->customer->wallet->update(['balance' => 50.00]);

        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(400);
    }

    public function test_duplicate_purchase_prevented(): void
    {
        Order::factory()->create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(409);
    }

    public function test_coupon_applied_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'expires_at' => now()->addDay(),
            'usage_limit' => 100,
            'status' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'coupon_code' => 'SAVE10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.discount', '90.00') // 10% of 900
            ->assertJsonPath('data.total', '810.00');
    }

    public function test_customer_can_view_their_orders(): void
    {
        Order::factory()->create([
            'user_id' => $this->customer->id,
            'total' => 900.00,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_quantity_exceeds_maximum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/orders', [
                'product_id' => $this->product->id,
                'quantity' => 4, // exceeds max 3
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }
}
```

## Implementation Order

### Phase 1: Essential APIs (30 minutes)
1. Add order routes
2. Complete StoreOrderRequest
3. Create Customer OrderController
4. Create OrderResource

### Phase 2: Admin APIs (20 minutes)
5. Create Admin OrderController
6. Add filtering logic

### Phase 3: Testing (1-2 hours)
7. Create OrderTest.php
8. Implement all test cases
9. Run tests and fix issues

### Phase 4: Refinements (30 minutes)
10. Add quantity validation to service
11. Add flash sale timing validation
12. Add order history queries

**Total estimated time: 2-3 hours**

## Ready to Implement?

All the missing components are clearly defined with complete code examples. Would you like me to implement any of these missing pieces?