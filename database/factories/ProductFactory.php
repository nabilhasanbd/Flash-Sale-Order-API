<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $flashSaleStart = fake()->dateTimeBetween('+1 day', '+1 month');
        $flashSaleEnd = (clone $flashSaleStart)->modify('+2 hours');

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 10, 5000),
            'available_stock' => fake()->numberBetween(0, 500),
            'flash_sale_start' => $flashSaleStart,
            'flash_sale_end' => $flashSaleEnd,
            'status' => fake()->randomElement([ProductStatus::Active, ProductStatus::Inactive]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Active,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Inactive,
        ]);
    }
}
