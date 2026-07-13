<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InactiveCouponException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Coupon is inactive.',
        ], 409);
    }
}