<?php

namespace App\Providers;

use App\Notifications\NotificationChannelManager;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationChannelManager::class, function ($app) {
            return new NotificationChannelManager();
        });
    }

    public function boot(): void
    {
        
    }
}