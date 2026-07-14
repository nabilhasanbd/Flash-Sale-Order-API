<?php

namespace App\Repositories;

use App\Interfaces\CouponRepositoryInterface;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class CouponRepository extends BaseRepository implements CouponRepositoryInterface
{
    public function __construct(Coupon $coupon)
    {
        parent::__construct($coupon);
    }

    public function findByCode(string $code): ?Coupon
    {
        return $this->model->where('code', $code)->first();
    }

    public function incrementUsage(int $couponId): bool
    {
        return $this->model->where('id', $couponId)->increment('used_count') > 0;
    }

    public function update(int $id, array $data): bool
    {
        $record = $this->find($id);

        if (! $record) {
            return false;
        }

        return $record->update($data);
    }

    public function save(Coupon $coupon): bool
    {
        return $coupon->save();
    }

    public function expireExpiredCoupons(): int
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->where('expires_at', '<', now())
            ->update(['status' => false]);
    }
}
