<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $timeout = 30;

    public function handle(OrderPlaced $event): void
    {
        SendNotificationJob::dispatch($event->order);
    }

    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        
    }
}