<?php

namespace App\Repositories;

use App\Interfaces\WalletRepositoryInterface;
use App\Models\User;
use App\Models\Wallet;

class WalletRepository extends BaseRepository implements WalletRepositoryInterface
{
    public function __construct(Wallet $wallet)
    {
        parent::__construct($wallet);
    }

    public function findById(int $id): ?Wallet
    {
        return $this->model->find($id);
    }

    public function findByUser(User $user): ?Wallet
    {
        return $this->model->where('user_id', $user->id)->first();
    }

    public function lockForUpdate(Wallet $wallet): ?Wallet
    {
        return $this->model
            ->whereKey($wallet->id)
            ->lockForUpdate()
            ->first();
    }

    public function updateBalance(Wallet $wallet, float $newBalance): bool
    {
        return $wallet->update(['balance' => $newBalance]);
    }
}
