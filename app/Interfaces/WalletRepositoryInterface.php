<?php

namespace App\Interfaces;

use App\Models\User;
use App\Models\Wallet;
use App\Repositories\BaseRepositoryInterface;

interface WalletRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?Wallet;

    public function findByUser(User $user): ?Wallet;

    public function lockForUpdate(Wallet $wallet): ?Wallet;

    public function updateBalance(Wallet $wallet, float $newBalance): bool;
}
