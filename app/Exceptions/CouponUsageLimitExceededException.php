<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class CouponUsageLimitExceededException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Coupon usage limit exceeded.',
        ], 409);
    }
}