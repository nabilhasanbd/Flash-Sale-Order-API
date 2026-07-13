<?php

namespace App\Services;

use App\Interfaces\CouponRepositoryInterface;

class CouponService
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepository,
    ) {}

    // Coupon business logic: validation, discount calculation, usage limits.
}
