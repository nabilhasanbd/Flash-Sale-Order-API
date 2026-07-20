<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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

    public function createOrderItem(array $orderItemData): OrderItem
    {
        return OrderItem::create($orderItemData);
    }

    public function updateOrderStatus(Order $order, OrderStatus $status, PaymentStatus $paymentStatus): bool
    {
        return $order->update([
            'status' => $status,
            'payment_status' => $paymentStatus,
        ]);
    }

    public function hasActivePurchaseForProduct(int $userId, int $productId): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
            ->exists();
    }

    public function decrementStock(Product $product, int $quantity): bool
    {
        return $product->decrement('available_stock', $quantity);
    }

    public function findProductById(int $productId): ?Product
    {
        return Product::find($productId);
    }

    public function getUserOrders(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->where('user_id', $userId)
            ->with(['user', 'coupon', 'orderItems.product'])
            ->latest()
            ->paginate(15);
    }

    public function getUserOrder(int $userId, int $orderId): ?Order
    {
        return $this->buildQuery([])
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->with(['user', 'coupon', 'orderItems.product'])
            ->first();
    }

    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->with(['user', 'coupon', 'orderItems.product'])
            ->latest()
            ->paginate(15);
    }

    public function getOrderWithRelations(int $orderId): ?Order
    {
        return Order::with(['user', 'coupon', 'orderItems.product'])
            ->find($orderId);
    }

    protected function buildQuery(array $filters): Builder
    {
        $query = Order::query();

        if (isset($filters['customer_id'])) {
            $query->where('user_id', $filters['customer_id']);
        }

        if (isset($filters['product_id'])) {
            $query->whereHas('orderItems', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('user', function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'ilike', '%'.$filters['search'].'%')
                        ->orWhere('email', 'ilike', '%'.$filters['search'].'%');
                })->orWhereHas('orderItems.product', function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'ilike', '%'.$filters['search'].'%');
                });
            });
        }

        return $query;
    }
}
