<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\Log;

class EmailChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        Log::info('Email notification channel called (not yet implemented)', [
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => get_class($notification),
            'email' => $notifiable->email ?? null
        ]);

        
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'mail';
    }
}