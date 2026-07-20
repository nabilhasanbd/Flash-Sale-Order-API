<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Debit',
            self::Credit => 'Credit',
        };
    }

    public function affectsBalance(): string
    {
        return match ($this) {
            self::Debit => 'subtraction',
            self::Credit => 'addition',
        };
    }
}
