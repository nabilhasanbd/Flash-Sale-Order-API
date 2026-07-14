# Queue Configuration and Commands

## Queue Connection

The application uses `database` queue connection as configured in `.env`:

```env
QUEUE_CONNECTION=database
```

## Queue Tables

The following tables are already created:

- `jobs` - Stores pending queue jobs
- `failed_jobs` - Stores failed queue jobs for retry
- `job_batches` - Stores job batching information
- `notifications` - Stores database notifications

## Queue Commands

### Start Queue Worker

Run the queue worker to process background jobs:

```bash
php artisan queue:work database
```

### Start Queue Worker in Background (Linux/Mac)

```bash
nohup php artisan queue:work database > storage/logs/queue-worker.log 2>&1 &
```

### Start Queue Worker with Monitoring

```bash
php artisan queue:work database --monitor
```

### Stop Queue Worker

```bash
php artisan queue:restart
```

### Retry Failed Jobs

```bash
php artisan queue:retry all
```

### Retry Specific Failed Job

```bash
php artisan queue:retry <job-id>
```

### Clear Failed Jobs

```bash
php artisan queue:flush
```

### List Failed Jobs

```bash
php artisan queue:failed
```

## Event-Driven Architecture

### OrderPlaced Event

Location: `app/Events/OrderPlaced.php`

Dispatched after successful order commit. Contains:
- Order model with loaded relationships (user, coupon, orderItems.product)

### SendNotificationListener

Location: `app/Listeners/SendNotificationListener.php`

- Listens for OrderPlaced event
- Dispatches SendNotificationJob
- Implements ShouldQueue for async processing
- Retries up to 3 times on failure
- Timeout: 30 seconds

### SendNotificationJob

Location: `app/Jobs/SendNotificationJob.php`

- Receives Order model from listener
- Loads user and order item data
- Creates and sends OrderPlacedNotification
- Implements retry logic with exponential backoff: [10, 30, 60] seconds
- Logs errors on failure

### OrderPlacedNotification

Location: `app/Notifications/OrderPlacedNotification.php`

- Database notification only (no email, no SMS)
- Returns structured data:
  ```json
  {
    "title": "Order Successful",
    "message": "Your flash sale purchase was successful.",
    "order_id": 15,
    "order_number": "15",
    "product": "iPhone 16",
    "quantity": 2,
    "amount": "1800.00",
    "purchase_time": "2026-07-14T12:54:02+06:00"
  }
  ```

## Event Registration

Laravel 12 uses automatic event discovery. Listeners are automatically discovered based on naming conventions:

- Event: `OrderPlaced`
- Listener: `SendNotificationListener` (suffix "Listener")
- Auto-registered via `OrderPlaced` event type hint

No manual registration needed in `EventServiceProvider`.

## OrderService Integration

Location: `app/Services/OrderService.php:133`

Event dispatch occurs after database commit:

```php
DB::afterCommit(function () use ($order) {
    event(new OrderPlaced($order));
});
```

This ensures:
- Order is fully committed before notification processing
- Notification failures never rollback the order
- Event is dispatched only on successful transaction

## Error Handling

### Listener Failure

- Failed listeners are logged
- Job remains in queue for retry
- Order remains successful

### Job Failure

- Failed jobs are stored in `failed_jobs` table
- Jobs retry up to 3 times with exponential backoff
- Errors are logged with full stack trace
- Order remains successful regardless of notification failure

### Notification Failure

- Notification class implements ShouldQueue
- Failed notifications are stored in failed jobs table
- User does not receive notification but order is complete

## Monitoring Queue Health

### Check Queue Status

```bash
php artisan queue:monitor database --max-wait-time=60
```

### View Queue Statistics

```bash
php artisan queue:stats
```

### Prune Old Jobs

```bash
php artisan queue:prune-failed --hours=24
php artisan queue:prune-failed --hours=48
```

## Production Considerations

### Supervisor Configuration (Linux)

Create `/etc/supervisor/conf.d/flash-sale-worker.conf`:

```ini
[program:flash-sale-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Restart supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start flash-sale-worker:*
```

### Queue Worker Recommendations

- Run multiple workers for high-volume periods
- Monitor queue depth to scale workers accordingly
- Set appropriate retry policies and timeouts
- Use queue monitoring tools (Laravel Horizon, Supervisor)
- Regularly prune failed and completed jobs
- Log queue worker activity for debugging

## Performance Optimization

### Batch Processing

Process multiple notifications in a single job for high-volume:

```php
Batch::dispatch(new SendNotificationJob($order1))
     ->add(new SendNotificationJob($order2))
     ->then();
```

### Queue Priority

Route notifications to different queues:

```bash
php artisan queue:work database --queue=high,default,low
```

### Memory Management

Restart workers after processing N jobs:

```bash
php artisan queue:work database --max-jobs=1000
```

## Testing Queue System

### Run Queue in Sync Mode

For testing, set `.env`:

```env
QUEUE_CONNECTION=sync
```

### Dispatch Test Job

```php
use App\Jobs\SendNotificationJob;
use App\Models\Order;

$order = Order::first();
SendNotificationJob::dispatch($order);
```

### Test Queue Processing

```bash
php artisan tinker
>>> use App\Events\OrderPlaced;
>>> use App\Models\Order;
>>> event(new OrderPlaced(Order::first()));
```