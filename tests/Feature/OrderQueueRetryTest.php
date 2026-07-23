<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessOrderPaymentJob;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderQueueRetryTest extends TestCase
{
    use RefreshDatabase;

    private function seedMerchantWallet(): User
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        Wallet::factory()->create(['user_id' => $merchant->id, 'balance' => 0]);

        return $merchant;
    }

    private function createPendingOrder(User $customer, Product $product, int $quantity = 1, float $balance = 10000): Order
    {
        Wallet::factory()->create(['user_id' => $customer->id, 'balance' => $balance]);

        $subtotal = (float) $product->price * $quantity;

        $order = Order::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'coupon_id' => null,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Pending,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => (float) $product->price,
            'subtotal' => $subtotal,
        ]);

        return $order;
    }

    private function createActiveProduct(int $stock = 10, float $price = 100.00): Product
    {
        return Product::factory()->active()->create([
            'available_stock' => $stock,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => $price,
        ]);
    }

    public function test_process_order_completes_a_pending_order(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct(stock: 10, price: 100.00);
        $order = $this->createPendingOrder($customer, $product, quantity: 2);

        $service = app(OrderService::class);
        $result = $service->processOrder($order);

        $this->assertSame(OrderStatus::Completed->value, $result->status->value);
        $this->assertSame(PaymentStatus::Paid->value, $result->payment_status->value);
        $this->assertSame(8, (int) $product->fresh()->available_stock);

        $payment = $result->paymentTransaction;
        $this->assertNotNull($payment);
        $this->assertSame(PaymentTransactionStatus::Success->value, $payment->status->value);

        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_process_order_is_idempotent_for_completed_order(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct(stock: 10);
        $order = $this->createPendingOrder($customer, $product);

        $service = app(OrderService::class);
        $service->processOrder($order);

        $stockBefore = (int) $product->fresh()->available_stock;
        $paymentCountBefore = DB::table('payment_transactions')->count();

        $service->processOrder($order->fresh());

        $this->assertSame($stockBefore, (int) $product->fresh()->available_stock);
        $this->assertSame($paymentCountBefore, DB::table('payment_transactions')->count());
    }

    public function test_fail_order_marks_order_failed_and_releases_idempotency_key(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct();
        $order = $this->createPendingOrder($customer, $product);

        IdempotencyKey::create([
            'user_id' => $customer->id,
            'idempotency_key' => 'test-key-123',
            'order_id' => $order->id,
            'request_hash' => ['hash' => 'abc'],
        ]);

        $service = app(OrderService::class);
        $service->failOrder($order, new \Exception('Permanent failure'));

        $this->assertSame(OrderStatus::Failed->value, $order->fresh()->status->value);
        $this->assertSame(PaymentStatus::Failed->value, $order->fresh()->payment_status->value);
        $this->assertDatabaseMissing('idempotency_keys', ['order_id' => $order->id]);
    }

    public function test_is_transient_detects_deadlock_sqlstate(): void
    {
        $service = app(OrderService::class);

        $deadlock = new \PDOException('deadlock detected');
        $deadlock->errorInfo = ['40P01'];
        $deadlockEx = new QueryException('test', 'SELECT 1', [], $deadlock);
        $this->assertTrue($service->isTransient($deadlockEx));

        $serialization = new \PDOException('serialization failure');
        $serialization->errorInfo = ['40001'];
        $serializationEx = new QueryException('test', 'SELECT 1', [], $serialization);
        $this->assertTrue($service->isTransient($serializationEx));

        $uniqueViolation = new \PDOException('unique violation');
        $uniqueViolation->errorInfo = ['23000'];
        $uniqueEx = new QueryException('test', 'SELECT 1', [], $uniqueViolation);
        $this->assertFalse($service->isTransient($uniqueEx));

        $this->assertFalse($service->isTransient(new \Exception('generic')));
    }

    public function test_job_processes_pending_order_to_completion(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct(stock: 5);
        $order = $this->createPendingOrder($customer, $product, quantity: 1);

        $job = new ProcessOrderPaymentJob($order);
        $job->handle(app(OrderService::class));

        $this->assertSame(OrderStatus::Completed->value, $order->fresh()->status->value);
        $this->assertSame(4, (int) $product->fresh()->available_stock);
    }

    public function test_job_marks_order_failed_on_permanent_exception(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct(stock: 5, price: 500.00);
        $order = $this->createPendingOrder($customer, $product, quantity: 1, balance: 100.00);

        $job = new ProcessOrderPaymentJob($order);
        $job->handle(app(OrderService::class));

        $this->assertSame(OrderStatus::Failed->value, $order->fresh()->status->value);
    }

    public function test_job_skips_already_completed_order(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createActiveProduct(stock: 5);
        $order = $this->createPendingOrder($customer, $product);

        $service = app(OrderService::class);
        $service->processOrder($order);

        $stockBefore = (int) $product->fresh()->available_stock;

        $job = new ProcessOrderPaymentJob($order->fresh());
        $job->handle(app(OrderService::class));

        $this->assertSame($stockBefore, (int) $product->fresh()->available_stock);
    }

    public function test_pending_order_response_returns_202(): void
    {
        $this->seedMerchantWallet();
        $customer = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 10000]);
        $product = $this->createActiveProduct();

        $order = Order::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'coupon_id' => null,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Pending,
        ]);

        $token = $customer->createToken('t')->plainTextToken;

        $response = $this->getJson('/api/orders/'.$order->id, [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
    }
}
