<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_side_effects_after_purchase(): void
    {
        // Seed merchant wallet (required by the transfer).
        $merchant = User::factory()->create(['role' => 'merchant']);
        Wallet::factory()->create(['user_id' => $merchant->id, 'balance' => 0]);

        $customer = User::factory()->create(['role' => 'customer']);
        $customerWallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 10000]);

        $product = Product::factory()->active()->create([
            'available_stock' => 10,
            'flash_sale_start' => now()->subHour(),
            'flash_sale_end' => now()->addHour(),
            'price' => 100.00,
        ]);

        $token = $customer->createToken('t')->plainTextToken;

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'sidefx-1']);

        $response->assertStatus(201);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(8, (int) $product->fresh()->available_stock); // 10 - 2
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 2); // debit + credit
        $this->assertLessThan(10000, (float) $customerWallet->fresh()->balance);

        // Notification row logged in DB (exactly one, not duplicated).
        $notificationCount = DB::table('notifications')->where('notifiable_id', $customer->id)->count();
        $this->assertSame(1, $notificationCount, 'Expected exactly one notification row after purchase.');
    }
}
