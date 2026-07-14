<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 1000.00,
                ]
            );
        }
    }
}