<?php

namespace App\Repositories;

use App\Interfaces\PaymentTransactionRepositoryInterface;
use App\Models\PaymentTransaction;

class PaymentTransactionRepository extends BaseRepository implements PaymentTransactionRepositoryInterface
{
    public function __construct(PaymentTransaction $paymentTransaction)
    {
        parent::__construct($paymentTransaction);
    }

    public function create(array $data): PaymentTransaction
    {
        return $this->model->create($data);
    }

    public function findByReference(string $reference): ?PaymentTransaction
    {
        return $this->model->where('reference', $reference)->first();
    }

    public function updateStatus(PaymentTransaction $paymentTransaction, string $status): bool
    {
        return $paymentTransaction->update(['status' => $status]);
    }
}
