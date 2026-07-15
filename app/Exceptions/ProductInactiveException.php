<?php

namespace App\Exceptions;

class ProductInactiveException extends \RuntimeException
{
    protected $message = 'This product is not currently available for purchase.';
}