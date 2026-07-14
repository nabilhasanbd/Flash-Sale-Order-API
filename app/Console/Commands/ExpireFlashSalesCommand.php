<?php

namespace App\Console\Commands;

use App\Services\FlashSaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireFlashSalesCommand extends Command
{
    protected $signature = 'flash-sales:expire';

    protected $description = 'Automatically expire finished flash sales';

    public function __construct(
        private FlashSaleService $flashSaleService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Flash Sale Scheduler Started');

        try {
            $stats = $this->flashSaleService->getSchedulerStats();

            $this->info("Products Expired: {$stats['products_expired']}");
            $this->info("Execution Time: {$stats['execution_time_seconds']} seconds");
            $this->info('Completed Successfully');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Flash Sale Scheduler Failed: ' . $e->getMessage());
            $this->error('Check logs for details: ' . storage_path('logs/laravel.log'));

            Log::error('ExpireFlashSalesCommand execution failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}