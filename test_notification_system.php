<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\OrderPlaced;
use App\Models\Order;
use Illuminate\Support\Facades\Event;

echo "=== Event-Driven Notification System Test ===\n\n";

echo "1. Checking Event Class...\n";
if (class_exists('App\Events\OrderPlaced')) {
    echo "   ✓ OrderPlaced event exists\n";
} else {
    echo "   ✗ OrderPlaced event not found\n";
}

echo "2. Checking Listener Class...\n";
if (class_exists('App\Listeners\SendNotificationListener')) {
    echo "   ✓ SendNotificationListener exists\n";
} else {
    echo "   ✗ SendNotificationListener not found\n";
}

echo "3. Checking Job Class...\n";
if (class_exists('App\Jobs\SendNotificationJob')) {
    echo "   ✓ SendNotificationJob exists\n";
} else {
    echo "   ✗ SendNotificationJob not found\n";
}

echo "4. Checking Notification Class...\n";
if (class_exists('App\Notifications\OrderPlacedNotification')) {
    echo "   ✓ OrderPlacedNotification exists\n";
} else {
    echo "   ✗ OrderPlacedNotification not found\n";
}

echo "5. Checking Queue Configuration...\n";
$queueConnection = config('queue.default');
echo "   Queue connection: {$queueConnection}\n";
if ($queueConnection === 'database') {
    echo "   ✓ Database queue configured\n";
} else {
    echo "   ✗ Database queue not configured\n";
}

echo "6. Checking Event-Listener Mapping...\n";
$listeners = Event::getListeners('App\Events\OrderPlaced');
if (!empty($listeners)) {
    echo "   ✓ Listeners registered for OrderPlaced event\n";
    foreach ($listeners as $listener) {
        if (is_array($listener)) {
            echo "   - " . get_class($listener[0]) . "\n";
        }
    }
} else {
    echo "   ! No listeners manually registered (Laravel 12 auto-discovery)\n";
}

echo "7. Checking Queue Tables...\n";
try {
    $tables = \DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name IN ('jobs', 'failed_jobs', 'notifications')");
    $requiredTables = ['jobs', 'failed_jobs', 'notifications'];
    $foundTables = array_column($tables, 'table_name');
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $foundTables)) {
            echo "   ✓ {$table} table exists\n";
        } else {
            echo "   ✗ {$table} table not found\n";
        }
    }
} catch (\Exception $e) {
    echo "   ! Could not check tables: " . $e->getMessage() . "\n";
}

echo "8. Testing Event Dispatch...\n";
try {
    $testOrder = Order::first();
    if ($testOrder) {
        echo "   - Test order found: ID {$testOrder->id}\n";
        
        // Create event instance
        $event = new OrderPlaced($testOrder);
        echo "   ✓ OrderPlaced event created\n";
        echo "   - Order ID: {$event->order->id}\n";
        echo "   - User ID: {$event->order->user_id}\n";
        echo "   - Total: {$event->order->total}\n";
        
        // Check relationships
        if ($event->order->relationLoaded('user')) {
            echo "   ✓ User relationship loaded\n";
        }
        if ($event->order->relationLoaded('coupon')) {
            echo "   ✓ Coupon relationship loaded\n";
        }
        if ($event->order->relationLoaded('orderItems')) {
            echo "   ✓ OrderItems relationship loaded\n";
        }
    } else {
        echo "   ! No orders found for testing\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Event dispatch failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nNext steps:\n";
echo "1. Run queue worker: php artisan queue:work database\n";
echo "2. Place an order to test the notification system\n";
echo "3. Check notifications table for results\n";
echo "4. Check failed_jobs table if notifications fail\n";
echo "\nFor detailed setup, see QUEUE_SETUP.md\n";