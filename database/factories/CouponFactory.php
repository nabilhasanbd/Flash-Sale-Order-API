<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????????')),
            'type' => fake()->randomElement([CouponType::Fixed, CouponType::Percentage]),
            'value' => fake()->randomFloat(2, 10, 500),
            'minimum_purchase' => fake()->randomFloat(2, 0, 100),
            'usage_limit' => fake()->numberBetween(1, 100),
            'used_count' => 0,
            'expires_at' => now()->addDays(fake()->numberBetween(1, 30)),
            'status' => true,
        ];
    }

    public function percentage(int $value = 50, float $minimumPurchase = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::Percentage,
            'value' => $value,
            'minimum_purchase' => $minimumPurchase,
        ]);
    }

    public function fixed(float $value = 100, float $minimumPurchase = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::Fixed,
            'value' => $value,
            'minimum_purchase' => $minimumPurchase,
        ]);
    }
}
