<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class CouponExpiredException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Coupon has expired.',
        ], 409);
    }
}