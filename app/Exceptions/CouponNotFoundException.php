<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class CouponNotFoundException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Coupon not found.',
        ], 404);
    }
}