<?php

namespace App\Console\Commands;

use App\Services\CouponExpirationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCouponsCommand extends Command
{
    protected $signature = 'coupons:expire';

    protected $description = 'Automatically expire expired coupons';

    public function __construct(
        private CouponExpirationService $couponExpirationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Coupon Scheduler Started');

        try {
            $stats = $this->couponExpirationService->getSchedulerStats();

            $this->info("Coupons Expired: {$stats['coupons_expired']}");
            $this->info("Execution Time: {$stats['execution_time_seconds']} seconds");
            $this->info('Completed Successfully');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Coupon Scheduler Failed: ' . $e->getMessage());
            $this->error('Check logs for details: ' . storage_path('logs/laravel.log'));

            Log::error('ExpireCouponsCommand execution failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}