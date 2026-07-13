<?php

namespace App\Services;

use App\Interfaces\WalletRepositoryInterface;

class WalletService
{
    public function __construct(
        protected WalletRepositoryInterface $walletRepository,
    ) {}

    // Wallet business logic: debit, credit, balance checks, transactions.
}
