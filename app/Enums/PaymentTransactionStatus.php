<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Reversed => 'Reversed',
            self::Refunded => 'Refunded',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Success,
            self::Failed,
            self::Reversed,
            self::Refunded,
        ], true);
    }
}
