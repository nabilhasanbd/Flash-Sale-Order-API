<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        Log::info('SMS notification channel called (not yet implemented)', [
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => get_class($notification),
            'phone' => $notifiable->phone ?? null
        ]);

        
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'sms';
    }
}