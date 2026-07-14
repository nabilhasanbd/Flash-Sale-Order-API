<?php

namespace App\Services;

use App\Interfaces\ProductRepositoryInterface;
use Illuminate\Support\Facades\Log;

class FlashSaleService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
    ) {}

    public function expireFinishedFlashSales(): int
    {
        $startTime = microtime(true);
        
        try {
            $updatedCount = $this->productRepository->expireFinishedFlashSales();
            
            $executionTime = round(microtime(true) - $startTime, 4);
            
            Log::info('Flash Sale Scheduler Started', [
                'timestamp' => now()->toIso8601String(),
                'execution_time_seconds' => $executionTime,
            ]);
            
            Log::info('Products Expired', [
                'count' => $updatedCount,
            ]);
            
            Log::info('Flash Sale Scheduler Completed Successfully', [
                'total_products_updated' => $updatedCount,
                'execution_time_seconds' => $executionTime,
            ]);
            
            return $updatedCount;
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 4);
            
            Log::error('Flash Sale Scheduler Failed', [
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
        
        $expiredCount = $this->productRepository->expireFinishedFlashSales();
        
        return [
            'products_expired' => $expiredCount,
            'execution_time_seconds' => round(microtime(true) - $startTime, 4),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}