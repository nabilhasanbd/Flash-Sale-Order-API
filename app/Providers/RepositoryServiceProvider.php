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

        $this->app->singleton(
            \App\Services\CouponService::class,
            function ($app) {
                return new \App\Services\CouponService(
                    $app->make(\App\Interfaces\CouponRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            \App\Services\WalletService::class,
            function ($app) {
                return new \App\Services\WalletService(
                    $app->make(\App\Interfaces\WalletRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            \App\Services\OrderService::class,
            function ($app) {
                return new \App\Services\OrderService(
                    $app->make(\App\Interfaces\OrderRepositoryInterface::class),
                    $app->make(\App\Interfaces\ProductRepositoryInterface::class),
                    $app->make(\App\Interfaces\WalletRepositoryInterface::class),
                    $app->make(\App\Interfaces\CouponRepositoryInterface::class),
                    $app->make(\App\Services\CouponService::class)
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
