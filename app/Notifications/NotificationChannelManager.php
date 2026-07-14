<?php

namespace App\Notifications;

use App\Notifications\Channels\DatabaseChannel;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\NotificationChannelInterface;
use Illuminate\Support\Collection;

class NotificationChannelManager
{
    protected array $channels = [];
    protected array $channelMap = [];

    public function __construct()
    {
        $this->registerDefaultChannels();
    }

    protected function registerDefaultChannels(): void
    {
        $this->registerChannel(new DatabaseChannel());
        $this->registerChannel(new EmailChannel());
        $this->registerChannel(new SmsChannel());
        $this->registerChannel(new PushChannel());
    }

    public function registerChannel(NotificationChannelInterface $channel): void
    {
        $this->channels[$channel->getName()] = $channel;
    }

    public function getChannel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    public function getEnabledChannels(): Collection
    {
        return collect($this->channels)
            ->filter(fn(NotificationChannelInterface $channel) => $channel->isEnabled());
    }

    public function getAvailableChannels(): Collection
    {
        return collect($this->channels);
    }

    public function isChannelEnabled(string $name): bool
    {
        $channel = $this->getChannel($name);
        return $channel ? $channel->isEnabled() : false;
    }

    public function sendToChannels(object $notifiable, OrderPlacedNotification $notification, array $channels = ['database']): void
    {
        foreach ($channels as $channelName) {
            $channel = $this->getChannel($channelName);
            
            if ($channel && $channel->isEnabled()) {
                try {
                    $channel->send($notifiable, $notification);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send {$channelName} notification", [
                        'error' => $e->getMessage(),
                        'channel' => $channelName,
                        'notifiable_id' => $notifiable->getKey()
                    ]);
                }
            }
        }
    }
}