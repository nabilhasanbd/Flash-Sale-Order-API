<?php

namespace App\Providers;

use App\Interfaces\CouponRepositoryInterface;
use App\Interfaces\OrderRepositoryInterface;
use App\Interfaces\PaymentTransactionRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\WalletRepositoryInterface;
use App\Interfaces\WalletTransactionRepositoryInterface;
use App\Repositories\CouponRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Repositories\ProductRepository;
use App\Repositories\WalletRepository;
use App\Repositories\WalletTransactionRepository;
use App\Services\CouponService;
use App\Services\CustomerProductService;
use App\Services\OrderService;
use App\Services\PaymentTransactionService;
use App\Services\WalletService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class,
        );

        $this->app->bind(
            OrderRepositoryInterface::class,
            OrderRepository::class,
        );

        $this->app->bind(
            WalletRepositoryInterface::class,
            WalletRepository::class,
        );

        $this->app->bind(
            WalletTransactionRepositoryInterface::class,
            WalletTransactionRepository::class,
        );

        $this->app->bind(
            PaymentTransactionRepositoryInterface::class,
            PaymentTransactionRepository::class,
        );

        $this->app->bind(
            CouponRepositoryInterface::class,
            CouponRepository::class,
        );

        $this->app->singleton(
            CustomerProductService::class,
            function ($app) {
                return new CustomerProductService(
                    $app->make(ProductRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            CouponService::class,
            function ($app) {
                return new CouponService(
                    $app->make(CouponRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            WalletService::class,
            function ($app) {
                return new WalletService(
                    $app->make(WalletRepositoryInterface::class),
                    $app->make(WalletTransactionRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            PaymentTransactionService::class,
            function ($app) {
                return new PaymentTransactionService(
                    $app->make(PaymentTransactionRepositoryInterface::class),
                    $app->make(WalletRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            OrderService::class,
            function ($app) {
                return new OrderService(
                    $app->make(OrderRepositoryInterface::class),
                    $app->make(ProductRepositoryInterface::class),
                    $app->make(CouponService::class),
                    $app->make(PaymentTransactionService::class),
                    $app->make(WalletService::class)
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
