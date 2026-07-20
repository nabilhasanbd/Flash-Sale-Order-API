<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidAmountException extends \Exception
{
    public function __construct(string $message = 'The transfer amount must be greater than zero.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
