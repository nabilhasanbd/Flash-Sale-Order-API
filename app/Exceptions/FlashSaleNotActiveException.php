<?php

namespace App\Exceptions;

class FlashSaleNotActiveException extends \RuntimeException
{
    protected $message = 'This flash sale is not currently active.';
}