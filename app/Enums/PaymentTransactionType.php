<?php

namespace App\Enums;

enum PaymentTransactionType: string
{
    case FlashSale = 'flash_sale';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::FlashSale => 'Flash Sale Purchase',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
            self::Transfer => 'Transfer',
        };
    }
}
