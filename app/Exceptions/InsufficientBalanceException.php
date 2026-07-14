<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InsufficientBalanceException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient wallet balance.',
        ], 409);
    }
}