<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

class EmailChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        $email = $notifiable->routeNotificationForMail($notification);
        
        if (!$email) {
            \Illuminate\Support\Facades\Log::warning('No email address found for notification', [
                'notifiable_id' => $notifiable->getKey(),
                'notification_type' => get_class($notification)
            ]);
            return;
        }

        $mailMessage = $this->buildMailMessage($notification);
        
        try {
            Mail::to($email)->send($mailMessage);
            
            \Illuminate\Support\Facades\Log::info('Email notification sent successfully', [
                'email' => $email,
                'notification_type' => get_class($notification),
                'order_id' => $notification->orderId
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send email notification', [
                'email' => $email,
                'error' => $e->getMessage(),
                'order_id' => $notification->orderId
            ]);
            throw $e;
        }
    }

    protected function buildMailMessage(OrderPlacedNotification $notification): Mailable
    {
        return new class($notification) extends Mailable
        {
            public function __construct(
                private OrderPlacedNotification $notification
            ) {}

            public function build()
            {
                return $this->view('emails.order_placed', [
                    'notification' => $this->notification
                ])
                ->subject('Order Successful - ' . $this->notification->orderNumber)
                ->from(config('notification.channels.mail.from_address'), config('notification.channels.mail.from_name'));
            }
        };
    }

    public function isEnabled(): bool
    {
        return config('notification.channels.mail.enabled', false) && 
               config('notification.enabled_channels', ['database']);
    }

    public function getName(): string
    {
        return 'mail';
    }
}