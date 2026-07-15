<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'nabil@stl.com'],
            [
                'name' => 'Nabil Hasan',
                'email' => 'nabil@stl.com',
                'password' => bcrypt('nabil@stl.com'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        $admin->wallet()->firstOrCreate([
            'balance' => 0,
        ]);

        $this->command->info('Admin user created successfully.');
        $this->command->info('Email: nabil@stl.com');
        $this->command->info('Password: nabil@stl.com');
    }
}