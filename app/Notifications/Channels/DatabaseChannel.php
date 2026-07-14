<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class DatabaseChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        $databaseMessage = $notification->toDatabase($notifiable);
        
        $notifiable->routeNotificationFor('database', $notification)->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'data' => $databaseMessage,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        Log::info('Database notification sent', [
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => get_class($notification)
        ]);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'database';
    }
}