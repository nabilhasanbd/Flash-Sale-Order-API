<?php

namespace App\Interfaces;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface WalletTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function create(array $data): WalletTransaction;

    public function findByPaymentTransaction(int $paymentTransactionId): Collection;

    public function paginateForWallet(Wallet $wallet, int $perPage = 15): LengthAwarePaginator;
}
