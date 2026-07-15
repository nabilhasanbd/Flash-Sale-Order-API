<?php

namespace App\Services;

use App\Http\Resources\OrderResource;
use App\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class OrderHistoryService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
    ) {}

    public function index(User $user): array
    {
        $orders = $this->orderRepository->getUserOrders($user->id);

        return [
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ];
    }

    public function show(User $user, int $orderId): array
    {
        $order = $this->orderRepository->getUserOrder($user->id, $orderId);

        if (!$order) {
            Log::warning('Order not found or access denied', [
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            return [
                'success' => false,
                'message' => 'Order not found.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Order details retrieved successfully.',
            'data' => new OrderResource($order),
        ];
    }

    public function adminIndex(array $filters = []): array
    {
        $orders = $this->orderRepository->getAllOrders($filters);

        return [
            'success' => true,
            'message' => 'All orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ];
    }

    public function adminShow(int $orderId): array
    {
        $order = $this->orderRepository->getOrderWithRelations($orderId);

        if (!$order) {
            Log::warning('Order not found', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => false,
                'message' => 'Order not found.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Order details retrieved successfully.',
            'data' => new OrderResource($order),
        ];
    }
}