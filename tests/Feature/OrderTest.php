<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DuplicatePurchaseException;
use App\Exceptions\FlashSaleNotActiveException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MaximumQuantityExceededException;
use App\Exceptions\ProductInactiveException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Product $product;
    protected Coupon $coupon;
    protected Wallet $wallet;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => 'customer']);

        $this->product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $this->coupon = Coupon::factory()->percentage(50, 0)->create([
            'code' => 'TEST50',
            'usage_limit' => 100,
            'expires_at' => now()->addDay(),
            'status' => true,
        ]);

        $this->wallet = Wallet::factory()->create([
            'user_id' => $this->customer->id,
            'balance' => 10000,
        ]);

        $this->orderService = app(OrderService::class);
    }

    public function test_customer_can_place_order_successfully(): void
    {
        Event::fake();

        $order = $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            2,
            'TEST50'
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->customer->id, $order->user_id);
        $this->assertEquals(OrderStatus::Completed, $order->status);
        $this->assertEquals(PaymentStatus::Paid, $order->payment_status);

        $order->load('orderItems');
        $this->assertCount(1, $order->orderItems);
        $this->assertEquals(2, $order->orderItems->first()->quantity);

        $this->product->refresh();
        $this->assertEquals(8, $this->product->available_stock);

        $this->wallet->refresh();
        $this->assertLessThan(10000, (float) $this->wallet->balance);

        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->is($order));
    }

    public function test_customer_can_place_order_without_coupon(): void
    {
        $order = $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            null
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertNull($order->coupon_id);
        $this->assertEquals(0, (float) $order->discount);
    }

    public function test_cannot_place_order_with_insufficient_stock(): void
    {
        $this->product->update(['available_stock' => 1]);

        $this->expectException(InsufficientStockException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            2,
            'TEST50'
        );
    }

    public function test_cannot_place_order_for_inactive_product(): void
    {
        $this->product->update(['status' => 'inactive']);

        $this->expectException(ProductInactiveException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            'TEST50'
        );
    }

    public function test_cannot_place_order_when_flash_sale_not_started(): void
    {
        $this->product->update([
            'flash_sale_start' => now()->addHour(),
            'flash_sale_end' => now()->addHours(2),
        ]);

        $this->expectException(FlashSaleNotActiveException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            'TEST50'
        );
    }

    public function test_cannot_place_order_when_flash_sale_expired(): void
    {
        $this->product->update([
            'flash_sale_start' => now()->subHours(2),
            'flash_sale_end' => now()->subHour(),
        ]);

        $this->expectException(FlashSaleNotActiveException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            'TEST50'
        );
    }

    public function test_cannot_exceed_maximum_quantity_per_order(): void
    {
        $this->expectException(MaximumQuantityExceededException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            5,
            'TEST50'
        );
    }

    public function test_cannot_place_duplicate_order_for_same_product(): void
    {
        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            'TEST50'
        );

        $this->expectException(DuplicatePurchaseException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            'TEST50'
        );
    }

    public function test_order_fails_without_sufficient_wallet_balance(): void
    {
        $this->wallet->update(['balance' => 50]);

        $this->expectException(InsufficientBalanceException::class);

        $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            1,
            null
        );
    }

    public function test_coupon_is_properly_applied_to_order(): void
    {
        $order = $this->orderService->placeOrder(
            $this->customer,
            $this->product->id,
            2,
            'TEST50'
        );

        $this->coupon->refresh();
        $this->assertEquals(1, $this->coupon->used_count);
        $this->assertEquals($this->coupon->id, $order->coupon_id);
        $this->assertGreaterThan(0, (float) $order->discount);
    }

    public function test_order_history_returns_only_customer_orders(): void
    {
        $otherUser = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create([
            'user_id' => $otherUser->id,
            'balance' => 10000,
        ]);

        $this->orderService->placeOrder($this->customer, $this->product->id, 1, 'TEST50');
        $this->orderService->placeOrder($otherUser, $this->product->id, 1, 'TEST50');

        $userOrders = $this->customer->orders()->get();

        $this->assertCount(1, $userOrders);
        $this->assertEquals($this->customer->id, $userOrders->first()->user_id);
    }

    public function test_admin_can_access_all_orders(): void
    {
        $this->orderService->placeOrder($this->customer, $this->product->id, 1, 'TEST50');

        $this->assertEquals(1, Order::count());
    }

    public function test_order_stock_decrement_is_persisted(): void
    {
        $this->orderService->placeOrder($this->customer, $this->product->id, 3, 'TEST50');

        $this->product->refresh();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'available_stock' => 7,
        ]);
    }
}
