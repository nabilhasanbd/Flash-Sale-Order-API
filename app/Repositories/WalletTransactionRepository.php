<?php

namespace App\Repositories;

use App\Interfaces\WalletTransactionRepositoryInterface;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class WalletTransactionRepository extends BaseRepository implements WalletTransactionRepositoryInterface
{
    public function __construct(WalletTransaction $walletTransaction)
    {
        parent::__construct($walletTransaction);
    }

    public function create(array $data): WalletTransaction
    {
        return $this->model->create($data);
    }

    public function findByPaymentTransaction(int $paymentTransactionId): Collection
    {
        return $this->model
            ->where('payment_transaction_id', $paymentTransactionId)
            ->orderBy('id')
            ->get();
    }

    public function paginateForWallet(Wallet $wallet, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('wallet_id', $wallet->id)
            ->latest()
            ->paginate($perPage);
    }
}
