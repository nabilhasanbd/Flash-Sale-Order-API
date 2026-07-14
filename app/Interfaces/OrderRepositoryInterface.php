<?php

namespace App\Interfaces;

use App\Models\Order;
use App\Models\Product;
use App\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface extends BaseRepositoryInterface
{
    public function createOrder(array $orderData): Order;

    public function createOrderItem(array $orderItemData): array;

    public function findByUserAndProduct(int $userId, int $productId): ?Order;

    public function decrementStock(Product $product, int $quantity): bool;

    public function findProductById(int $productId): ?Product;
}
