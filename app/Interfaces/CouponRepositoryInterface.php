<?php

namespace App\Interfaces;

use App\Models\Coupon;
use App\Repositories\BaseRepositoryInterface;

interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCode(string $code): ?Coupon;

    public function incrementUsage(int $couponId): bool;

    public function update(int $id, array $data): bool;

    public function save(Coupon $coupon): bool;
}
