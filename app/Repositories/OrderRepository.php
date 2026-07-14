<?php

namespace App\Repositories;

use App\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $order)
    {
        parent::__construct($order);
    }

    public function createOrder(array $orderData): Order
    {
        return Order::create($orderData);
    }

    public function createOrderItem(array $orderItemData): array
    {
        return OrderItem::create($orderItemData);
    }

    public function findByUserAndProduct(int $userId, int $productId): ?Order
    {
        return Order::where('user_id', $userId)
            ->whereHas('orderItems', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->where('status', 'completed')
            ->first();
    }

    public function decrementStock(Product $product, int $quantity): bool
    {
        return $product->decrement('available_stock', $quantity);
    }

    public function findProductById(int $productId): ?Product
    {
        return Product::find($productId);
    }
}
