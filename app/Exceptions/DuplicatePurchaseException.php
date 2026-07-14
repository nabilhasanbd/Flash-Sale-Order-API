<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class DuplicatePurchaseException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You have already purchased this product during this flash sale.',
        ], 409);
    }
}