<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InsufficientStockException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient stock available.',
        ], 409);
    }
}