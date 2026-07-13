<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        Coupon::create([
            'code' => 'SAVE10',
            'type' => CouponType::Percentage->value,
            'value' => 10,
            'minimum_purchase' => 100,
            'usage_limit' => 100,
            'used_count' => 0,
            'expires_at' => $now->copy()->addDays(30),
            'status' => true,
        ]);

        Coupon::create([
            'code' => 'FIXED50',
            'type' => CouponType::Fixed->value,
            'value' => 50,
            'minimum_purchase' => 200,
            'usage_limit' => 50,
            'used_count' => 0,
            'expires_at' => $now->copy()->addDays(15),
            'status' => true,
        ]);

        Coupon::create([
            'code' => 'SUMMER20',
            'type' => CouponType::Percentage->value,
            'value' => 20,
            'minimum_purchase' => 500,
            'usage_limit' => 200,
            'used_count' => 0,
            'expires_at' => $now->copy()->addDays(60),
            'status' => true,
        ]);

        Coupon::create([
            'code' => 'EXPIRED100',
            'type' => CouponType::Percentage->value,
            'value' => 100,
            'minimum_purchase' => 100,
            'usage_limit' => 10,
            'used_count' => 0,
            'expires_at' => $now->copy()->subDays(1),
            'status' => true,
        ]);

        Coupon::create([
            'code' => 'LIMITED5',
            'type' => CouponType::Fixed->value,
            'value' => 5,
            'minimum_purchase' => 50,
            'usage_limit' => 3,
            'used_count' => 3,
            'expires_at' => $now->copy()->addDays(30),
            'status' => true,
        ]);

        Coupon::create([
            'code' => 'INACTIVE25',
            'type' => CouponType::Percentage->value,
            'value' => 25,
            'minimum_purchase' => 100,
            'usage_limit' => 100,
            'used_count' => 0,
            'expires_at' => $now->copy()->addDays(30),
            'status' => false,
        ]);

        Coupon::create([
            'code' => 'HIGHVALUE',
            'type' => CouponType::Fixed->value,
            'value' => 1000,
            'minimum_purchase' => 2000,
            'usage_limit' => 20,
            'used_count' => 0,
            'expires_at' => $now->copy()->addDays(90),
            'status' => true,
        ]);
    }
}