# Quick Start Guide - Extensible Notification System

## Current Status

✅ **Database notifications** - Active and production-ready  
🔮 **Email notifications** - Ready for implementation  
🔮 **SMS notifications** - Ready for implementation  
🔮 **Push notifications** - Ready for implementation

## Architecture Overview

```
OrderService (No Changes)
    ↓
OrderPlaced Event (No Changes)  
    ↓
SendNotificationListener (No Changes)
    ↓
SendNotificationJob (No Changes - supports channels)
    ↓
NotificationChannelManager (New)
    ↓
Multiple Channels:
    - DatabaseChannel ✅
    - EmailChannel 🔮
    - SmsChannel 🔮
    - PushChannel 🔮
```

## How It Works

### Current Flow (Database Only)

1. Order created → DB commit → Event dispatched
2. SendNotificationListener catches event
3. SendNotificationJob prepares notification data
4. OrderPlacedNotification sent via ['database']
5. DatabaseChannel stores notification in database

### Future Flow (Multi-Channel)

1. Same as above, but step 4 uses ['database', 'mail', 'sms', 'push']
2. Each enabled channel receives the notification
3. Channels process independently
4. Failures don't affect other channels

## Adding Email Notifications

### Step 1: Enable Email Channel

Edit `config/notification.php`:

```php
'enabled_channels' => ['database', 'mail'],
'mail' => ['enabled' => true],
```

### Step 2: Implement EmailChannel

Replace `app/Notifications/Channels/EmailChannel.php` with content from `EmailChannelExample.php`:

```php
public function send(object $notifiable, OrderPlacedNotification $notification): void
{
    $email = $notifiable->routeNotificationForMail($notification);
    Mail::to($email)->send($this->buildMailMessage($notification));
}

public function isEnabled(): bool
{
    return config('notification.channels.mail.enabled', false);
}
```

### Step 3: Configure Mail Service

In `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@flashsale.com
MAIL_FROM_NAME=Flash Sale
```

### Step 4: Update Notification Channels

Edit `app/Notifications/OrderPlacedNotification.php`:

```php
public function via(object $notifiable): array
{
    return config('notification.enabled_channels', ['database']);
}
```

### Step 5: Test

```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $order = App\Models\Order::first();
>>> event(new App\Events\OrderPlaced($order));
>>> // Check notifications table and email inbox
```

## Adding SMS Notifications

### Step 1: Enable SMS Channel

Edit `config/notification.php`:

```php
'enabled_channels' => ['database', 'mail', 'sms'],
'sms' => ['enabled' => true],
```

### Step 2: Add Phone Field to Users

```bash
php artisan make:migration add_phone_to_users_table
```

```php
$table->string('phone')->nullable();
```

```bash
php artisan migrate
```

### Step 3: Implement SmsChannel

Edit `app/Notifications/Channels/SmsChannel.php`:

```php
use Twilio\Rest\Client;

public function send(object $notifiable, OrderPlacedNotification $notification): void
{
    $phone = $notifiable->routeNotificationForSms($notification);
    
    $message = "Order {$notification->orderNumber} successful! " .
               "{$notification->productName} x{$notification->quantity} = " .
               "\${$notification->amount}";
    
    $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
    $twilio->messages->create($phone, [
        'from' => env('TWILIO_FROM'),
        'body' => $message
    ]);
}

public function isEnabled(): bool
{
    return config('notification.channels.sms.enabled', false);
}
```

### Step 4: Configure Twilio

In `.env`:

```env
TWILIO_SID=your_account_sid
TWILIO_TOKEN=your_auth_token
TWILIO_FROM=+1234567890
```

## Adding Push Notifications

### Step 1: Enable Push Channel

Edit `config/notification.php`:

```php
'enabled_channels' => ['database', 'mail', 'sms', 'push'],
'push' => ['enabled' => true],
```

### Step 2: Add Device Tokens Storage

```bash
php artisan make:migration add_device_tokens_to_users_table
```

```php
$table->json('device_tokens')->nullable();
```

```bash
php artisan migrate
```

### Step 3: Implement PushChannel

Edit `app/Notifications/Channels/PushChannel.php`:

```php
use Kreait\Firebase\Factory;

public function send(object $notifiable, OrderPlacedNotification $notification): void
{
    $deviceTokens = $notifiable->routeNotificationForPush($notification);
    
    $factory = (new Factory)->withServiceAccount(env('FIREBASE_CREDENTIALS'));
    $messaging = $factory->createMessaging();
    
    $message = \Kreait\Firebase\Messaging\CloudMessage::new()
        ->withNotification([
            'title' => 'Order Successful',
            'body' => "Your {$notification->productName} purchase was successful"
        ])
        ->withData([
            'order_id' => (string) $notification->orderId,
            'amount' => (string) $notification->amount
        ]);
    
    $messaging->sendMulticast($message, $deviceTokens);
}

public function isEnabled(): bool
{
    return config('notification.channels.push.enabled', false);
}
```

## Testing the System

### Verify All Components

```bash
php verify_extensible_system.php
```

### Test Channel Availability

```bash
php artisan tinker
>>> $manager = app(App\Notifications\NotificationChannelManager::class);
>>> $manager->getAvailableChannels()->map->getName();
// ["database", "mail", "sms", "push"]
```

### Test Enabled Channels

```bash
php artisan tinker
>>> $manager->getEnabledChannels()->map->getName();
// ["database"]
```

### Test Full Flow

```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $order = App\Models\Order::first();
>>> event(new App\Events\OrderPlaced($order));
>>> $user->notifications()->latest()->first()->data;
// View notification data
```

## Configuration Management

### Enable Channels via Config

```php
// config/notification.php
'enabled_channels' => ['database', 'mail'],
'channels' => [
    'database' => ['enabled' => true],
    'mail' => ['enabled' => true],
    'sms' => ['enabled' => false],
    'push' => ['enabled' => false],
],
```

### Enable Channels via Environment

```env
NOTIFICATION_CHANNELS=database,mail
NOTIFICATION_MAIL_ENABLED=true
NOTIFICATION_SMS_ENABLED=false
NOTIFICATION_PUSH_ENABLED=false
```

### Per-Channel Retry Settings

```php
'retry' => [
    'database' => ['tries' => 3, 'backoff' => [10, 30, 60]],
    'mail' => ['tries' => 3, 'backoff' => [10, 30, 60]],
    'sms' => ['tries' => 3, 'backoff' => [10, 30, 60]],
    'push' => ['tries' => 3, 'backoff' => [10, 30, 60]],
],
```

## Queue Worker Commands

### Start Queue Worker

```bash
php artisan queue:work database
```

### Monitor Queue Health

```bash
php artisan queue:monitor database --max-wait-time=60
```

### Retry Failed Jobs

```bash
php artisan queue:retry all
```

### View Failed Jobs

```bash
php artisan queue:failed
```

## Error Handling

### Channel Failures

Individual channel failures are logged but don't affect other channels:

```php
Log::error("Failed to send mail notification", [
    'error' => $e->getMessage(),
    'channel' => 'mail'
]);
// Continues with other channels
```

### Job-Level Retry

If all channels fail, the job is retried according to configuration:

```php
public int $tries = 3;
public array $backoff = [10, 30, 60];
```

### Failed Jobs Table

Check failed jobs:

```bash
php artisan tinker
>>> DB::table('failed_jobs')->get();
```

## Monitoring

### Check Queue Status

```bash
php artisan queue:stats
```

### View Queue Logs

```bash
tail -f storage/logs/laravel.log
```

### Monitor Queue Performance

```bash
php artisan queue:work database --monitor
```

## Best Practices

### 1. Gradual Channel Addition

Start with database, then add channels one at a time.

### 2. Test in Staging

Always test new channels in staging environment first.

### 3. Monitor Performance

Monitor queue performance after adding each channel.

### 4. Rate Limiting

Implement rate limiting for external channels (SMS, email).

### 5. Error Logging

Ensure comprehensive logging for all channel operations.

## Troubleshooting

### Channels Not Sending

1. Check configuration: `config('notification.enabled_channels')`
2. Verify channel is enabled: `config('notification.channels.{name}.enabled')`
3. Check queue worker is running
4. Review logs: `tail storage/logs/laravel.log`

### Queue Jobs Failing

1. Check failed jobs: `php artisan queue:failed`
2. Review error messages in logs
3. Verify retry configuration
4. Test queue worker manually

### Notifications Not Appearing

1. Check notifications table: `DB::table('notifications')->latest()->get()`
2. Verify user has notifications relationship
3. Check notification delivery logs
4. Test notification class directly

## Resources

- Full documentation: `EXTENSIBLE_NOTIFICATION_SYSTEM.md`
- Implementation summary: `EXTENSIBLE_SUMMARY.md`
- Queue setup: `QUEUE_SETUP.md`
- System verification: `verify_extensible_system.php`

## Summary

The extensible notification system is production-ready for database notifications and fully prepared for email, SMS, and push notifications. Adding new channels requires:

1. Enable channel in configuration
2. Implement channel class
3. Configure external service
4. Test delivery

**No changes required to:** OrderService, SendNotificationListener, OrderPlaced Event ✅