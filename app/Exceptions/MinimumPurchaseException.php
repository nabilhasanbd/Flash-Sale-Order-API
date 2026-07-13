<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class MinimumPurchaseException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Minimum purchase amount not satisfied.',
        ], 422);
    }
}