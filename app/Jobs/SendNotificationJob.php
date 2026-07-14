<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly Order $order,
        public readonly array $channels = ['database']
    ) {}

    public function handle(): void
    {
        $user = $this->order->user;

        if (!$user) {
            Log::error('User not found for order', ['order_id' => $this->order->id]);
            return;
        }

        $orderItems = $this->order->orderItems;
        $firstItem = $orderItems->first();

        if (!$firstItem) {
            Log::error('No order items found for order', ['order_id' => $this->order->id]);
            return;
        }

        $notificationData = $this->prepareNotificationData($firstItem);
        $notification = new OrderPlacedNotification(
            orderId: $notificationData['order_id'],
            orderNumber: $notificationData['order_number'],
            productName: $notificationData['product_name'],
            quantity: $notificationData['quantity'],
            amount: $notificationData['amount'],
            purchaseTime: $notificationData['purchase_time'],
            customerEmail: $notificationData['customer_email'],
            customerName: $notificationData['customer_name']
        );

        try {
            $user->notify($notification);
            Log::info('Order notification sent successfully', [
                'order_id' => $this->order->id,
                'user_id' => $user->id,
                'channels' => $this->channels
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order notification', [
                'order_id' => $this->order->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'channels' => $this->channels
            ]);
            throw $e;
        }
    }

    protected function prepareNotificationData($orderItem): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->id,
            'product_name' => $orderItem->product->name ?? 'Unknown Product',
            'quantity' => $orderItem->quantity,
            'amount' => $this->order->total,
            'purchase_time' => $this->order->created_at->toIso8601String(),
            'customer_email' => $this->order->user->email ?? '',
            'customer_name' => $this->order->user->name ?? 'Customer',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed permanently', [
            'order_id' => $this->order->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'channels' => $this->channels
        ]);
    }
}
