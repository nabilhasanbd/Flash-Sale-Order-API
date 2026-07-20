<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Wallet',
        };
    }
}
