<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\Log;

class PushChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        Log::info('Push notification channel called (not yet implemented)', [
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => get_class($notification),
            'device_tokens' => $notifiable->deviceTokens ?? []
        ]);

        
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'push';
    }
}