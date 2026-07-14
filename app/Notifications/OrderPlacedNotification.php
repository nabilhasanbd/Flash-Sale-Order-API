<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $productName,
        public readonly int $quantity,
        public readonly float $amount,
        public readonly string $purchaseTime,
        public readonly string $customerEmail,
        public readonly string $customerName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Order Successful',
            'message' => 'Your flash sale purchase was successful.',
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'product' => $this->productName,
            'quantity' => $this->quantity,
            'amount' => number_format($this->amount, 2),
            'purchase_time' => $this->purchaseTime,
            'type' => 'order_placed',
        ]);
    }

    public function toMail(object $notifiable): array
    {
        return [];
    }

    public function toSms(object $notifiable): array
    {
        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order Successful',
            'message' => 'Your flash sale purchase was successful.',
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'product' => $this->productName,
            'quantity' => $this->quantity,
            'amount' => number_format($this->amount, 2),
            'purchase_time' => $this->purchaseTime,
        ];
    }

    protected function getNotificationData(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'product' => $this->productName,
            'quantity' => $this->quantity,
            'amount' => number_format($this->amount, 2),
            'purchase_time' => $this->purchaseTime,
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName,
        ];
    }
}