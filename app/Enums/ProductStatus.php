<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case SoldOut = 'sold_out';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::SoldOut => 'Sold Out',
            self::Draft => 'Draft',
        };
    }
}
