<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'iPhone 16',
            'description' => 'Latest Apple Phone',
            'price' => 1200,
            'available_stock' => 100,
            'flash_sale_start' => now()->subHour()->format('Y-m-d H:i:s'),
            'flash_sale_end' => now()->addHour()->format('Y-m-d H:i:s'),
            'status' => 'active',
        ], $overrides);
    }

    private function availableProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'status' => 'active',
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
        ], $overrides));
    }

    // ---------- Admin APIs ----------

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJson('/api/admin/products', $this->validPayload(), $this->authHeader($admin));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('data.name', 'iPhone 16')
            ->assertJsonPath('data.price', '1200.00')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('products', ['name' => 'iPhone 16', 'status' => 'active']);
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->create();

        $response = $this->postJson('/api/admin/products', $this->validPayload(), $this->authHeader($customer));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_product(): void
    {
        $response = $this->postJson('/api/admin/products', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_admin_can_list_products_with_search_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Product::factory()->create(['name' => 'iPhone 16', 'status' => 'active']);
        Product::factory()->create(['name' => 'Samsung Galaxy', 'status' => 'inactive']);
        Product::factory()->create(['name' => 'iPhone 15', 'status' => 'active']);

        $response = $this->getJson('/api/admin/products?search=iPhone&status=active', $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_any_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'iPhone 16', 'status' => 'inactive']);

        $response = $this->getJson('/api/admin/products/'.$product->id, $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'iPhone 15', 'price' => 1000]);

        $response = $this->putJson(
            '/api/admin/products/'.$product->id,
            ['name' => 'iPhone 16', 'price' => 1200],
            $this->authHeader($admin)
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'iPhone 16')
            ->assertJsonPath('data.price', '1200.00');
    }

    public function test_customer_cannot_update_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->putJson(
            '/api/admin/products/'.$product->id,
            ['name' => 'Updated'],
            $this->authHeader($customer)
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/admin/products/'.$product->id, [], $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_customer_cannot_delete_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/admin/products/'.$product->id, [], $this->authHeader($customer));

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJson('/api/admin/products', [], $this->authHeader($admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'price',
                'available_stock',
                'flash_sale_start',
                'flash_sale_end',
                'status',
            ]);
    }

    public function test_store_validates_flash_sale_end_is_after_start(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->validPayload([
            'flash_sale_start' => '2026-07-20 12:00:00',
            'flash_sale_end' => '2026-07-20 10:00:00',
        ]);

        $response = $this->postJson('/api/admin/products', $payload, $this->authHeader($admin));

        $response->assertStatus(422)->assertJsonValidationErrors(['flash_sale_end']);
    }

    // ---------- Customer APIs ----------

    public function test_guest_can_list_available_flash_sale_products(): void
    {
        $this->availableProduct(['name' => 'Live Deal']);
        $this->availableProduct(['status' => 'inactive', 'name' => 'Hidden']);
        $this->availableProduct(['flash_sale_end' => now()->subMinute(), 'name' => 'Expired']);
        $this->availableProduct(['available_stock' => 0, 'name' => 'Sold Out']);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Live Deal', $response->json('data.0.name'));
    }

    public function test_customer_can_view_available_product_details(): void
    {
        $product = $this->availableProduct(['name' => 'iPhone 16']);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'iPhone 16');
    }

    public function test_customer_cannot_view_inactive_product_details(): void
    {
        $product = $this->availableProduct(['status' => 'inactive']);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $response = $this->getJson('/api/products/9999');

        $response->assertStatus(404);
    }
}
