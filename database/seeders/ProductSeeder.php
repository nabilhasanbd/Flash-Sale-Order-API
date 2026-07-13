<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        Product::create([
            'name' => 'iPhone 16 Pro Max',
            'description' => 'Latest Apple flagship smartphone with A18 chip',
            'price' => 1199.99,
            'available_stock' => 50,
            'flash_sale_start' => $now->copy()->subHour(),
            'flash_sale_end' => $now->copy()->addHour(),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'description' => 'Premium Android flagship with S Pen',
            'price' => 999.99,
            'available_stock' => 30,
            'flash_sale_start' => $now->copy()->subHours(2),
            'flash_sale_end' => $now->copy()->addHours(2),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'MacBook Pro 16-inch M3 Max',
            'description' => 'Most powerful MacBook ever made',
            'price' => 2499.99,
            'available_stock' => 20,
            'flash_sale_start' => $now->copy()->subMinutes(30),
            'flash_sale_end' => $now->copy()->addMinutes(90),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'iPad Air 5th Generation',
            'description' => 'Versatile tablet with M1 chip',
            'price' => 599.99,
            'available_stock' => 100,
            'flash_sale_start' => $now->copy()->subHour(),
            'flash_sale_end' => $now->copy()->addHours(3),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Sony PlayStation 5',
            'description' => 'Next-gen gaming console with 4K gaming',
            'price' => 499.99,
            'available_stock' => 0,
            'flash_sale_start' => $now->copy()->subHour(),
            'flash_sale_end' => $now->copy()->addHour(),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Apple Watch Series 9',
            'description' => 'Advanced smartwatch with health monitoring',
            'price' => 399.99,
            'available_stock' => 75,
            'flash_sale_start' => $now->copy()->addHours(5),
            'flash_sale_end' => $now->copy()->addHours(7),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Nike Air Jordan 1 Retro',
            'description' => 'Classic basketball sneakers',
            'price' => 180.00,
            'available_stock' => 200,
            'flash_sale_start' => $now->copy()->subHours(3),
            'flash_sale_end' => $now->copy()->subHours(1),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Dell XPS 15',
            'description' => 'Premium Windows laptop',
            'price' => 1599.99,
            'available_stock' => 15,
            'flash_sale_start' => $now->copy()->addDays(10),
            'flash_sale_end' => $now->copy()->addDays(11),
            'status' => 'inactive',
        ]);

        Product::create([
            'name' => 'Canon EOS R6 Mark II',
            'description' => 'Professional mirrorless camera',
            'price' => 2299.99,
            'available_stock' => 10,
            'flash_sale_start' => $now->copy()->addDays(5),
            'flash_sale_end' => $now->copy()->addDays(6),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Nintendo Switch OLED',
            'description' => 'Hybrid gaming console with OLED screen',
            'price' => 349.99,
            'available_stock' => 60,
            'flash_sale_start' => $now->copy()->addDays(7),
            'flash_sale_end' => $now->copy()->addDays(8),
            'status' => 'inactive',
        ]);

        Product::create([
            'name' => 'Bose QuietComfort 45',
            'description' => 'Premium noise-canceling headphones',
            'price' => 279.99,
            'available_stock' => 40,
            'flash_sale_start' => $now->copy()->addDays(2),
            'flash_sale_end' => $now->copy()->addDays(3),
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Dyson V15 Detect',
            'description' => 'Advanced cordless vacuum cleaner',
            'price' => 649.99,
            'available_stock' => 25,
            'flash_sale_start' => $now->copy()->subDays(2),
            'flash_sale_end' => $now->copy()->subDay(),
            'status' => 'active',
        ]);
    }
}