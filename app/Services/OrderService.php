<?php

namespace App\Services;

use App\Interfaces\OrderRepositoryInterface;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
    ) {}

    // Order business logic: place order, stock reservation, status transitions.
}
