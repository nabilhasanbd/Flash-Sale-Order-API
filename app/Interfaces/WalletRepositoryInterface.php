<?php

namespace App\Interfaces;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WalletRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUser(User $user): ?Wallet;

    public function lockWallet(User $user): ?Wallet;

    public function updateBalance(Wallet $wallet, float $newBalance): bool;

    public function createTransaction(array $transactionData): WalletTransaction;

    public function getTransactions(Wallet $wallet, int $perPage = 15): LengthAwarePaginator;
}
