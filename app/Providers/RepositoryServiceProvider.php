<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Interfaces\ProductRepositoryInterface::class,
            \App\Repositories\ProductRepository::class,
        );

        $this->app->bind(
            \App\Interfaces\OrderRepositoryInterface::class,
            \App\Repositories\OrderRepository::class,
        );

        $this->app->bind(
            \App\Interfaces\WalletRepositoryInterface::class,
            \App\Repositories\WalletRepository::class,
        );

        $this->app->bind(
            \App\Interfaces\CouponRepositoryInterface::class,
            \App\Repositories\CouponRepository::class,
        );

        $this->app->singleton(
            \App\Services\CustomerProductService::class,
            function ($app) {
                return new \App\Services\CustomerProductService(
                    $app->make(\App\Interfaces\ProductRepositoryInterface::class)
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
