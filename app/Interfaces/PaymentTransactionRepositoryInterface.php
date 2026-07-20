<?php

namespace App\Interfaces;

use App\Models\PaymentTransaction;
use App\Repositories\BaseRepositoryInterface;

interface PaymentTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function create(array $data): PaymentTransaction;

    public function findByReference(string $reference): ?PaymentTransaction;

    public function updateStatus(PaymentTransaction $paymentTransaction, string $status): bool;
}
