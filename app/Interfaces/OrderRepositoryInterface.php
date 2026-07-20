<?php

namespace App\Interfaces;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface extends BaseRepositoryInterface
{
    public function createOrder(array $orderData): Order;

    public function createOrderItem(array $orderItemData): OrderItem;

    public function updateOrderStatus(Order $order, OrderStatus $status, PaymentStatus $paymentStatus): bool;

    public function findByUserAndProduct(int $userId, int $productId): ?Order;

    public function decrementStock(Product $product, int $quantity): bool;

    public function findProductById(int $productId): ?Product;

    public function getUserOrders(int $userId, array $filters = []): LengthAwarePaginator;

    public function getUserOrder(int $userId, int $orderId): ?Order;

    public function getAllOrders(array $filters = []): LengthAwarePaginator;

    public function getOrderWithRelations(int $orderId): ?Order;
}
