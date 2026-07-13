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

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'iPhone 16',
            'description' => 'Latest Apple Phone',
            'price' => 1200,
            'available_stock' => 100,
            'flash_sale_start' => '2026-07-20 10:00:00',
            'flash_sale_end' => '2026-07-20 12:00:00',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload, $this->authHeader($admin));

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => [
                    'name' => 'iPhone 16',
                    'description' => 'Latest Apple Phone',
                    'price' => '1200.00',
                    'available_stock' => 100,
                    'flash_sale_start' => '2026-07-20 10:00:00',
                    'flash_sale_end' => '2026-07-20 12:00:00',
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'iPhone 16',
            'status' => 'active',
        ]);
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->create();

        $payload = [
            'name' => 'iPhone 16',
            'price' => 1200,
            'available_stock' => 100,
            'flash_sale_start' => '2026-07-20 10:00:00',
            'flash_sale_end' => '2026-07-20 12:00:00',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload, $this->authHeader($customer));

        $response->assertStatus(403);
    }

    public function test_admin_can_list_products_with_search_filter_and_pagination(): void
    {
        $admin = User::factory()->admin()->create();

        Product::factory()->create(['name' => 'iPhone 16', 'status' => 'active']);
        Product::factory()->create(['name' => 'Samsung Galaxy', 'status' => 'inactive']);
        Product::factory()->create(['name' => 'iPhone 15', 'status' => 'active']);

        $response = $this->getJson('/api/products?search=iPhone&status=active', $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Products retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.data');
    }

    public function test_customer_cannot_list_products(): void
    {
        $customer = User::factory()->create();

        $response = $this->getJson('/api/products', $this->authHeader($customer));

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_view_product_details(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'iPhone 16']);

        $response = $this->getJson('/api/products/'.$product->id, $this->authHeader($customer));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product retrieved successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'iPhone 16',
                ],
            ]);
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $customer = User::factory()->create();

        $response = $this->getJson('/api/products/9999', $this->authHeader($customer));

        $response->assertStatus(404);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'name' => 'iPhone 15',
            'price' => 1000,
        ]);

        $payload = [
            'name' => 'iPhone 16',
            'price' => 1200,
        ];

        $response = $this->putJson('/api/products/'.$product->id, $payload, $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product updated successfully.',
                'data' => [
                    'name' => 'iPhone 16',
                    'price' => '1200.00',
                ],
            ]);
    }

    public function test_customer_cannot_update_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->putJson(
            '/api/products/'.$product->id,
            ['name' => 'Updated Name'],
            $this->authHeader($customer),
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/products/'.$product->id, [], $this->authHeader($admin));

        $response->assertStatus(200)
            ->assertExactJson([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_customer_cannot_delete_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/products/'.$product->id, [], $this->authHeader($customer));

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJson('/api/products', [], $this->authHeader($admin));

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

        $payload = [
            'name' => 'iPhone 16',
            'price' => 1200,
            'available_stock' => 100,
            'flash_sale_start' => '2026-07-20 12:00:00',
            'flash_sale_end' => '2026-07-20 10:00:00',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/products', $payload, $this->authHeader($admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['flash_sale_end']);
    }

    public function test_guest_cannot_access_products(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }
}
