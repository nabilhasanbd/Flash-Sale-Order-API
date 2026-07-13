<?php

use App\Enums\CouponType;

if (! function_exists('api_success')) {
    function api_success(mixed $data = null, string $message = 'Success', int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}

if (! function_exists('api_error')) {
    function api_error(string $message = 'Error', int $status = 400, mixed $errors = null): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}

if (! function_exists('format_money')) {
    function format_money(float $amount, string $currency = 'USD'): string
    {
        return number_format($amount, 2).' '.$currency;
    }
}

if (! function_exists('calculate_coupon_discount')) {
    function calculate_coupon_discount(CouponType $type, float $value, float $orderTotal): float
    {
        return match ($type) {
            CouponType::Fixed => min($value, $orderTotal),
            CouponType::Percentage => round($orderTotal * ($value / 100), 2),
        };
    }
}
