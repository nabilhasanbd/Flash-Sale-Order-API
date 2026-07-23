<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOrderPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [5, 10, 30, 60];

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(OrderService $orderService): void
    {
        $fresh = $this->order->fresh();

        if ($fresh->status === $fresh->status::Completed) {
            Log::info('ProcessOrderPaymentJob skipped: order already completed', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        try {
            $orderService->processOrder($fresh);
        } catch (QueryException $e) {
            if ($orderService->isTransient($e)) {
                throw $e;
            }

            $orderService->failOrder($fresh, $e);
        } catch (\Throwable $e) {
            $orderService->failOrder($fresh, $e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOrderPaymentJob exhausted all retries', [
            'order_id' => $this->order->id,
            'exception' => $exception->getMessage(),
        ]);

        $fresh = $this->order->fresh();

        if ($fresh->status !== $fresh->status::Completed) {
            app(OrderService::class)->failOrder($fresh, $exception);
        }
    }
}
