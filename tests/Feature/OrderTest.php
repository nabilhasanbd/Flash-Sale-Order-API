<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use App\Exceptions\DuplicatePurchaseException;
use App\Exceptions\FlashSaleNotActiveException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MaximumQuantityExceededException;
use App\Exceptions\ProductInactiveException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'customer']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    
    $this->product = Product::factory()->create([
        'stock' => 10,
        'flash_sale_enabled' => true,
        'flash_sale_starts_at' => now()->subHour(),
        'flash_sale_ends_at' => now()->addHour(),
        'flash_sale_max_quantity_per_order' => 3
    ]);

    $this->coupon = Coupon::factory()->create([
        'code' => 'TEST50',
        'type' => 'percentage',
        'value' => 50,
        'is_active' => true,
        'expires_at' => now()->addDay(),
        'usage_limit' => 100,
        'min_purchase_amount' => 100
    ]);

    $this->wallet = Wallet::factory()->create([
        'user_id' => $this->user->id,
        'balance' => 10000
    ]);
});

test('customer can create an order successfully', function () {
    Queue::fake();
    
    $orderService = app(OrderService::class);
    
    $order = $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        2,
        'TEST50'
    );

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->user_id)->toBe($this->user->id)
        ->and($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid');

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->quantity)->toBe(2);

    $this->product->refresh();
    expect($this->product->stock)->toBe(8);

    $this->wallet->refresh();
    expect($this->wallet->balance)->toBeLessThan(10000);

    Queue::assertPushed(\App\Jobs\SendNotificationJob::class);
});

test('cannot create order with insufficient stock', function () {
    $this->product->update(['stock' => 1]);

    $orderService = app(OrderService::class);

    $this->expectException(InsufficientStockException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        2,
        'TEST50'
    );
});

test('cannot create order for inactive product', function () {
    $this->product->update(['status' => 'inactive']);

    $orderService = app(OrderService::class);

    $this->expectException(ProductInactiveException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );
});

test('cannot create order when flash sale not started', function () {
    $this->product->update([
        'flash_sale_starts_at' => now()->addHour(),
        'flash_sale_ends_at' => now()->addHours(2)
    ]);

    $orderService = app(OrderService::class);

    $this->expectException(FlashSaleNotActiveException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );
});

test('cannot create order when flash sale expired', function () {
    $this->product->update([
        'flash_sale_starts_at' => now()->subHours(2),
        'flash_sale_ends_at' => now()->subHour()
    ]);

    $orderService = app(OrderService::class);

    $this->expectException(FlashSaleNotActiveException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );
});

test('cannot exceed maximum quantity per order', function () {
    $orderService = app(OrderService::class);

    $this->expectException(MaximumQuantityExceededException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        5,
        'TEST50'
    );
});

test('cannot exceed maximum quantity per order with custom limit', function () {
    $this->product->update(['flash_sale_max_quantity_per_order' => 2]);

    $orderService = app(OrderService::class);

    $this->expectException(MaximumQuantityExceededException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        3,
        'TEST50'
    );
});

test('cannot create duplicate order for same product', function () {
    $orderService = app(OrderService::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );

    $this->expectException(DuplicatePurchaseException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );
});

test('cannot exceed maximum quantity per order', function () {
    $orderService = app(OrderService::class);

    $this->expectException(InsufficientStockException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        5,
        'TEST50'
    );
});

test('order creation fails without sufficient wallet balance', function () {
    $this->wallet->update(['balance' => 50]);

    $orderService = app(OrderService::class);

    $this->expectException(\App\Exceptions\InsufficientBalanceException::class);

    $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );
});

test('concurrent orders handle race conditions correctly', function () {
    $this->product->update(['stock' => 2]);

    $orderService = app(OrderService::class);

    DB::beginTransaction();

    $firstOrder = $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        1,
        'TEST50'
    );

    $this->product->refresh();

    $secondUser = User::factory()->create(['role' => 'customer']);
    Wallet::factory()->create([
        'user_id' => $secondUser->id,
        'balance' => 10000
    ]);

    try {
        $orderService->createOrder(
            $secondUser->id,
            $this->product->id,
            2,
            'TEST50'
        );
        $this->fail('Expected InsufficientStockException');
    } catch (InsufficientStockException $e) {
        DB::rollBack();
    }

    $this->product->refresh();
    expect($this->product->stock)->toBe(1);
});

test('order history returns only customer orders', function () {
    $otherUser = User::factory()->create(['role' => 'customer']);

    $orderService = app(OrderService::class);

    $orderService->createOrder($this->user->id, $this->product->id, 1, 'TEST50');
    $orderService->createOrder($otherUser->id, $this->product->id, 1, 'TEST50');

    $userOrders = $this->user->orders()->get();
    expect($userOrders)->toHaveCount(1);
    expect($userOrders->first()->user_id)->toBe($this->user->id);
});

test('admin can access all orders', function () {
    $orderService = app(OrderService::class);

    $orderService->createOrder($this->user->id, $this->product->id, 1, 'TEST50');

    $allOrders = Order::all();
    expect($allOrders)->toHaveCount(1);
});

test('coupon is properly applied to order', function () {
    $orderService = app(OrderService::class);

    $order = $orderService->createOrder(
        $this->user->id,
        $this->product->id,
        2,
        'TEST50'
    );

    $this->coupon->refresh();
    expect($this->coupon->times_used)->toBe(1);
    expect($order->coupon_id)->toBe($this->coupon->id);
});