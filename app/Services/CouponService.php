<?php

namespace App\Services;

use App\Exceptions\CouponExpiredException;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InactiveCouponException;
use App\Exceptions\MinimumPurchaseException;
use App\Interfaces\CouponRepositoryInterface;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepository,
    ) {}

    public function validateCoupon(string $couponCode, float $subtotal): Coupon
    {
        $coupon = $this->couponRepository->findByCode($couponCode);

        if ($coupon === null) {
            throw new CouponNotFoundException();
        }

        if (! $coupon->status) {
            throw new InactiveCouponException();
        }

        if ($coupon->expires_at < now()) {
            throw new CouponExpiredException();
        }

        if ($coupon->used_count >= $coupon->usage_limit) {
            throw new CouponUsageLimitExceededException();
        }

        if ($subtotal < $coupon->minimum_purchase) {
            throw new MinimumPurchaseException();
        }

        return $coupon;
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal): array
    {
        $discount = 0;

        if ($coupon->type === \App\Enums\CouponType::Percentage) {
            $discount = ($subtotal * $coupon->value) / 100;
        } else {
            $discount = $coupon->value;
        }

        $discount = min($discount, $subtotal);

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'final_amount' => number_format($subtotal - $discount, 2, '.', ''),
        ];
    }

    public function incrementUsage(int $couponId): bool
    {
        return $this->couponRepository->incrementUsage($couponId);
    }
}