<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Merchant = 'merchant';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Merchant => 'Merchant',
            self::Customer => 'Customer',
        };
    }
}
