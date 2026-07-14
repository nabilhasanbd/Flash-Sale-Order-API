<?php

namespace App\Repositories;

use App\Interfaces\WalletRepositoryInterface;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WalletRepository extends BaseRepository implements WalletRepositoryInterface
{
    public function __construct(Wallet $wallet)
    {
        parent::__construct($wallet);
    }

    public function findByUser(User $user): ?Wallet
    {
        return $this->model->where('user_id', $user->id)->first();
    }

    public function lockWallet(User $user): ?Wallet
    {
        return $this->model->where('user_id', $user->id)->lockForUpdate()->first();
    }

    public function updateBalance(Wallet $wallet, float $newBalance): bool
    {
        return $wallet->update(['balance' => $newBalance]);
    }

    public function createTransaction(array $transactionData): WalletTransaction
    {
        return WalletTransaction::create($transactionData);
    }

    public function getTransactions(Wallet $wallet, int $perPage = 15): LengthAwarePaginator
    {
        return $wallet->transactions()->paginate($perPage);
    }
}
