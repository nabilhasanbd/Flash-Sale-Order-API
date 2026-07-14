# Extensible Notification System

## Overview

The notification system is designed with a modular, extensible architecture that supports multiple notification channels without modifying the OrderService or Listener. New notification channels can be added easily by implementing the channel interface and registering them.

## Architecture

```
SendNotificationJob
    ↓
OrderPlacedNotification
    ↓
NotificationChannelManager
    ↓
Multiple Channels (Database, Email, SMS, Push)
    ↓
各自通道发送
```

## Core Components

### 1. NotificationChannelInterface

**Location:** `app/Notifications/Channels/NotificationChannelInterface.php`

Interface that all notification channels must implement:

```php
interface NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void;
    public function isEnabled(): bool;
    public function getName(): string;
}
```

### 2. DatabaseChannel ✅ (Implemented)

**Location:** `app/Notifications/Channels/DatabaseChannel.php`

- Currently enabled and functional
- Stores notifications in `notifications` table
- Uses Laravel's standard database notification system

### 3. EmailChannel 🔮 (Future)

**Location:** `app/Notifications/Channels/EmailChannel.php`

- Stub implementation ready for future use
- Currently disabled (`isEnabled()` returns false)
- Will integrate with Laravel's Mail system when activated

### 4. SmsChannel 🔮 (Future)

**Location:** `app/Notifications/Channels/SmsChannel.php`

- Stub implementation ready for future use
- Currently disabled (`isEnabled()` returns false)
- Will integrate with SMS providers (Twilio, Nexmo, etc.) when activated

### 5. PushChannel 🔮 (Future)

**Location:** `app/Notifications/Channels/PushChannel.php`

- Stub implementation ready for future use
- Currently disabled (`isEnabled()` returns false)
- Will integrate with push services (FCM, APNs) when activated

### 6. NotificationChannelManager

**Location:** `app/Notifications/NotificationChannelManager.php`

Manages notification channels and routing:

- Registers available channels
- Determines which channels are enabled
- Sends notifications through enabled channels
- Handles channel failures gracefully

### 7. Configuration

**Location:** `config/notification.php`

Centralized configuration for all notification channels:

```php
return [
    'enabled_channels' => ['database'],
    
    'channels' => [
        'database' => ['enabled' => true, ...],
        'mail' => ['enabled' => false, ...],
        'sms' => ['enabled' => false, ...],
        'push' => ['enabled' => false, ...],
    ],
    
    'retry' => [
        'database' => ['tries' => 3, 'backoff' => [10, 30, 60]],
        // ... other channels
    ],
];
```

## Current Implementation

### Active Channel: Database

The database channel is currently the only enabled channel:

```php
// OrderPlacedNotification
public function via(object $notifiable): array
{
    return ['database'];
}
```

## Future Channel Implementation

### Adding Email Notifications

1. **Enable Email Channel in Config:**

```php
// config/notification.php
'enabled_channels' => ['database', 'mail'],
'mail' => ['enabled' => true, ...]
```

2. **Implement EmailChannel:**

```php
class EmailChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        Mail::to($notifiable->email)->send(new OrderPlacedEmail($notification));
    }
    
    public function isEnabled(): bool
    {
        return config('notification.channels.mail.enabled', false);
    }
}
```

3. **Add Email Method to Notification:**

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Order Successful')
        ->greeting('Hello ' . $this->customerName)
        ->line('Your flash sale purchase was successful.')
        ->line('Order: ' . $this->orderNumber)
        ->line('Product: ' . $this->productName)
        ->line('Quantity: ' . $this->quantity)
        ->line('Amount: $' . number_format($this->amount, 2))
        ->line('Thank you for your purchase!');
}
```

### Adding SMS Notifications

1. **Enable SMS Channel in Config:**

```php
'enabled_channels' => ['database', 'mail', 'sms'],
'sms' => ['enabled' => true, 'provider' => 'twilio']
```

2. **Implement SmsChannel:**

```php
class SmsChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        $phoneNumber = $notifiable->routeNotificationForSms();
        $message = $this->buildSmsMessage($notification);
        
        Twilio::message($phoneNumber, $message);
    }
    
    protected function buildSmsMessage($notification): string
    {
        return "Order {$notification->orderNumber} successful! {$notification->productName} x{$notification->quantity} = \${$notification->amount}";
    }
}
```

### Adding Push Notifications

1. **Enable Push Channel in Config:**

```php
'enabled_channels' => ['database', 'mail', 'sms', 'push'],
'push' => ['enabled' => true, 'provider' => 'fcm']
```

2. **Implement PushChannel:**

```php
class PushChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, OrderPlacedNotification $notification): void
    {
        $deviceTokens = $notifiable->routeNotificationForPush();
        $payload = $this->buildPushPayload($notification);
        
        FCM::sendToDevices($deviceTokens, $payload);
    }
    
    protected function buildPushPayload($notification): array
    {
        return [
            'title' => 'Order Successful',
            'body' => "Your {$notification->productName} purchase was successful",
            'data' => [
                'order_id' => $notification->orderId,
                'amount' => $notification->amount
            ]
        ];
    }
}
```

## Extensibility Benefits

### No Changes to Core Logic

Adding new notification channels requires no changes to:
- OrderService - business logic remains untouched
- SendNotificationListener - event handling unchanged
- SendNotificationJob - job processing remains the same

### Easy Channel Addition

To add a new channel:

1. Implement `NotificationChannelInterface`
2. Register channel in `NotificationChannelManager`
3. Configure in `config/notification.php`
4. Enable in OrderPlacedNotification `via()` method

### Graceful Degradation

- Channel failures don't affect other channels
- Fallback channels can be configured
- Individual channel retry policies

### Configuration-Driven

- Enable/disable channels via config
- Channel-specific settings
- Environment-based configuration

## Configuration Examples

### Database Only (Current)

```php
'enabled_channels' => ['database'],
'database' => ['enabled' => true],
```

### Database + Email

```php
'enabled_channels' => ['database', 'mail'],
'database' => ['enabled' => true],
'mail' => ['enabled' => true],
```

### All Channels

```php
'enabled_channels' => ['database', 'mail', 'sms', 'push'],
'database' => ['enabled' => true],
'mail' => ['enabled' => true],
'sms' => ['enabled' => true],
'push' => ['enabled' => true],
```

### Environment-Based

```env
NOTIFICATION_CHANNELS=database,mail
NOTIFICATION_MAIL_ENABLED=true
NOTIFICATION_SMS_ENABLED=false
NOTIFICATION_PUSH_ENABLED=false
```

## User Model Routing

The User model includes routing methods for all future channels:

```php
// Database (built-in)
public function notifications() { /* ... */ }

// Email
public function routeNotificationForMail($notification = null)
{
    return $this->email;
}

// SMS
public function routeNotificationForSms($notification = null)
{
    return $this->phone ?? null;
}

// Push
public function routeNotificationForPush($notification = null)
{
    return $this->deviceTokens ?? [];
}
```

## Channel Priority

Channels can be prioritized by configuration order:

```php
'channels' => [
    'database' => ['priority' => 1],  // First
    'mail' => ['priority' => 2],      // Second
    'sms' => ['priority' => 3],       // Third
    'push' => ['priority' => 4],      // Fourth
],
```

## Error Handling

### Individual Channel Failures

Each channel handles its own errors:

```php
try {
    $channel->send($notifiable, $notification);
} catch (\Exception $e) {
    Log::error("Failed to send {$channelName} notification", [
        'error' => $e->getMessage(),
        'channel' => $channelName
    ]);
    // Continue with other channels
}
```

### Global Channel Failure

If all channels fail, the job is retried according to retry configuration:

```php
'retry' => [
    'database' => ['tries' => 3, 'backoff' => [10, 30, 60]],
    'mail' => ['tries' => 3, 'backoff' => [10, 30, 60]],
    // ...
],
```

## Testing Channel Implementation

### Test Database Channel

```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $user->notify(new App\Notifications\OrderPlacedNotification(/*...*/));
>>> $user->notifications()->latest()->first()->data;
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

## Migration Path

### Phase 1: Database Only ✅
- Current implementation
- Reliable and efficient

### Phase 2: Add Email 🔮
- Implement EmailChannel
- Update User model with email routing
- Add email templates
- Configure mail settings

### Phase 3: Add SMS 🔮
- Implement SmsChannel
- Update User model with phone field
- Configure SMS provider
- Add SMS templates

### Phase 4: Add Push 🔮
- Implement PushChannel
- Update User model with device tokens
- Configure push provider
- Add push templates

## Best Practices

### Channel Independence
- Each channel should be self-contained
- No cross-channel dependencies
- Individual retry policies

### Error Isolation
- Channel failures don't affect other channels
- Graceful degradation
- Comprehensive logging

### Configuration Management
- Centralized configuration
- Environment-specific settings
- Easy enable/disable

### Testing Strategy
- Test each channel independently
- Test channel combinations
- Test failure scenarios
- Test configuration changes

## Performance Considerations

### Queue Optimization
- Each channel can have its own queue priority
- Separate queue workers for different channels
- Batch processing for high-volume notifications

### Rate Limiting
- Per-channel rate limiting
- User-specific rate limiting
- Provider-specific rate limits

### Caching
- Channel configuration caching
- User routing method caching
- Provider token caching

## Security Considerations

### Data Privacy
- Sensitive data protection
- User consent management
- Channel-specific data handling

### Access Control
- Channel authorization
- User notification preferences
- Permission management

### Audit Trail
- Notification delivery logging
- Channel usage tracking
- Failure incident logging

## Conclusion

The extensible notification system provides a robust foundation for multi-channel notifications while maintaining clean architecture and easy extensibility. New channels can be added without modifying core business logic, following SOLID principles and Laravel best practices.