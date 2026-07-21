<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function seedMerchantWallet(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        Wallet::factory()->create(['user_id' => $merchant->id, 'balance' => 0]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMerchantWallet();
    }

    public function test_non_customer_is_forbidden_from_placing_order(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], array_merge($this->authHeader($admin), ['Idempotency-Key' => 'admin-1']));

        $response->assertStatus(403);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_endpoint_is_rate_limited(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $headers = array_merge($this->authHeader($customer), ['Idempotency-Key' => 'rate-1']);

        // Drain the allowance (10/min). Payload is invalid (no merchant wallet
        // needed) but throttle middleware counts every request regardless.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/orders', ['product_id' => 999, 'quantity' => 1], $headers);
        }

        // 11th request within the same minute is throttled.
        $response = $this->postJson('/api/orders', ['product_id' => 999, 'quantity' => 1], $headers);

        $response->assertStatus(429);
    }

    protected function tearDown(): void
    {
        // Clear the limiter so it does not bleed into other tests in the suite.
        RateLimiter::clear(sha1('orders'));

        parent::tearDown();
    }
}
