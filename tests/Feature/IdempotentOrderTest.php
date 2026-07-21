<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotentOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Merchant wallet required by the wallet-to-wallet transfer.
        $merchant = User::factory()->create(['role' => 'merchant']);
        Wallet::factory()->create(['user_id' => $merchant->id, 'balance' => 0]);
    }

    private function authHeader(User $user): array
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    private function orderPayload(Product $product, int $quantity = 1): array
    {
        return [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ];
    }

    public function test_order_can_be_created_via_api_with_idempotency_key(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 10000]);
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $response = $this->postJson(
            '/api/orders',
            $this->orderPayload($product),
            array_merge($this->authHeader($user), ['Idempotency-Key' => 'key-123'])
        );

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Order placed successfully.']);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => 'key-123',
            'user_id' => $user->id,
        ]);
    }

    public function test_duplicate_request_with_same_key_creates_only_one_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 10000]);
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $headers = array_merge($this->authHeader($user), ['Idempotency-Key' => 'same-key']);

        $first = $this->postJson('/api/orders', $this->orderPayload($product), $headers);
        $second = $this->postJson('/api/orders', $this->orderPayload($product), $headers);

        $first->assertStatus(201);
        $second->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Order already processed.']);

        $this->assertDatabaseCount('orders', 1);
        $firstOrderId = $first->json('data.id');
        $this->assertEquals($firstOrderId, $second->json('data.id'));
    }

    public function test_request_without_idempotency_key_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
        ]);

        $response = $this->postJson(
            '/api/orders',
            $this->orderPayload($product),
            $this->authHeader($user)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_same_key_with_different_payload_returns_conflict(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 10000]);
        $productA = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);
        $productB = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $headers = array_merge($this->authHeader($user), ['Idempotency-Key' => 'shared-key']);

        $this->postJson('/api/orders', $this->orderPayload($productA), $headers)->assertStatus(201);
        $this->postJson('/api/orders', $this->orderPayload($productB), $headers)->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_failed_order_releases_key_so_client_can_retry(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 50]);
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $headers = array_merge($this->authHeader($user), ['Idempotency-Key' => 'retry-key']);

        // First attempt fails due to insufficient balance (409).
        $this->postJson('/api/orders', $this->orderPayload($product), $headers)->assertStatus(409);

        // Top up wallet and retry with the same key -> should now succeed.
        Wallet::where('user_id', $user->id)->update(['balance' => 10000]);

        $this->postJson('/api/orders', $this->orderPayload($product), $headers)->assertStatus(201);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_concurrent_requests_with_same_key_create_one_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 10000]);
        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $headers = array_merge($this->authHeader($user), ['Idempotency-Key' => 'race-key']);

        $first = $this->postJson('/api/orders', $this->orderPayload($product), $headers);
        $second = $this->postJson('/api/orders', $this->orderPayload($product), $headers);

        // At least one must succeed and only a single order exists.
        $this->assertTrue($first->status() === 201 || $second->status() === 201);
        $this->assertDatabaseCount('orders', 1);
    }
}
