<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Notifications\Notification;

interface NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void;
    
    public function isEnabled(): bool;
    
    public function getName(): string;
}