<?php

namespace App\Exceptions;

class MaximumQuantityExceededException extends \RuntimeException
{
    protected $message = 'You have exceeded the maximum quantity allowed per order.';

    public function __construct(int $maxQuantity, int $attemptedQuantity)
    {
        $this->message = "Maximum {$maxQuantity} units allowed per order. You attempted to purchase {$attemptedQuantity} units.";
        parent::__construct($this->message);
    }
}