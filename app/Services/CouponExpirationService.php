<?php

namespace App\Services;

use App\Interfaces\CouponRepositoryInterface;
use Illuminate\Support\Facades\Log;

class CouponExpirationService
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepository,
    ) {}

    public function expireExpiredCoupons(): int
    {
        $startTime = microtime(true);
        
        try {
            $updatedCount = $this->couponRepository->expireExpiredCoupons();
            
            $executionTime = round(microtime(true) - $startTime, 4);
            
            Log::info('Coupon Scheduler Started', [
                'timestamp' => now()->toIso8601String(),
                'execution_time_seconds' => $executionTime,
            ]);
            
            Log::info('Coupons Expired', [
                'count' => $updatedCount,
            ]);
            
            Log::info('Coupon Scheduler Completed Successfully', [
                'total_coupons_updated' => $updatedCount,
                'execution_time_seconds' => $executionTime,
            ]);
            
            return $updatedCount;
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 4);
            
            Log::error('Coupon Scheduler Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_seconds' => $executionTime,
            ]);
            
            throw $e;
        }
    }

    public function getSchedulerStats(): array
    {
        $startTime = microtime(true);
        
        $expiredCount = $this->couponRepository->expireExpiredCoupons();
        
        return [
            'coupons_expired' => $expiredCount,
            'execution_time_seconds' => round(microtime(true) - $startTime, 4),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}