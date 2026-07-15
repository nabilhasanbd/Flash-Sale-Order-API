<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Order History Module Verification ===\n\n";

echo "1. Checking Controller Classes...\n";
$customerControllerClass = 'App\Http\Controllers\Customer\OrderController';
$adminControllerClass = 'App\Http\Controllers\Admin\OrderController';

if (class_exists($customerControllerClass)) {
    echo "   ✓ CustomerOrderController exists\n";
} else {
    echo "   ✗ CustomerOrderController not found\n";
}

if (class_exists($adminControllerClass)) {
    echo "   ✓ AdminOrderController exists\n";
} else {
    echo "   ✗ AdminOrderController not found\n";
}

echo "\n2. Checking Service Class...\n";
$serviceClass = 'App\Services\OrderHistoryService';
if (class_exists($serviceClass)) {
    echo "   ✓ OrderHistoryService exists\n";
} else {
    echo "   ✗ OrderHistoryService not found\n";
}

echo "\n3. Checking Repository Interface...\n";
$interface = 'App\Interfaces\OrderRepositoryInterface';
if (interface_exists($interface)) {
    $reflection = new ReflectionClass($interface);
    $methods = ['getUserOrders', 'getUserOrder', 'getAllOrders', 'getOrderWithRelations'];
    
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ {$method}() exists\n";
        } else {
            echo "   ✗ {$method}() not found\n";
        }
    }
} else {
    echo "   ✗ OrderRepositoryInterface not found\n";
}

echo "\n4. Checking API Resource...\n";
$resourceClass = 'App\Http\Resources\OrderResource';
if (class_exists($resourceClass)) {
    echo "   ✓ OrderResource exists\n";
} else {
    echo "   ✗ OrderResource not found\n";
}

echo "\n5. Checking Middleware...\n";
$middlewareClass = 'App\Http\Middleware\AdminMiddleware';
if (class_exists($middlewareClass)) {
    echo "   ✓ AdminMiddleware exists\n";
} else {
    echo "   ✗ AdminMiddleware not found\n";
}

echo "\n6. Checking Policy...\n";
$policyClass = 'App\Policies\OrderPolicy';
if (class_exists($policyClass)) {
    $reflection = new ReflectionClass($policyClass);
    $methods = ['view', 'viewAny', 'create', 'update', 'delete'];
    
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ {$method}() exists\n";
        } else {
            echo "   ✗ {$method}() not found\n";
        }
    }
} else {
    echo "   ✗ OrderPolicy not found\n";
}

echo "\n7. Checking Routes...\n";
$router = app('router');
$routes = $router->getRoutes()->getRoutesByType();

$orderRoutes = [
    'GET /api/orders',
    'GET /api/orders/{order}',
    'GET /api/admin/orders',
    'GET /api/admin/orders/{order}',
];

foreach ($orderRoutes as $routePath) {
    $found = false;
    foreach ($routes['api'] ?? [] as $route) {
        if (strpos($route->uri, $routePath) !== false || 
            (strpos($routePath, '{order}') !== false && strpos($route->uri, 'orders/') !== false)) {
            $found = true;
            echo "   ✓ {$routePath} registered\n";
            break;
        }
    }
    if (!$found) {
        echo "   ✗ {$routePath} not registered\n";
    }
}

echo "\n8. Testing Service Dependency Injection...\n";
try {
    $service = app(App\Services\OrderHistoryService::class);
    echo "   ✓ OrderHistoryService can be instantiated\n";
} catch (\Exception $e) {
    echo "   ✗ OrderHistoryService instantiation failed: " . $e->getMessage() . "\n";
}

echo "\n9. Testing Repository Methods...\n";
try {
    $repository = app(\App\Repositories\OrderRepository::class);
    $reflection = new ReflectionClass($repository);
    $methods = ['getUserOrders', 'getUserOrder', 'getAllOrders', 'getOrderWithRelations', 'buildQuery'];
    
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ {$method}() exists in repository\n";
        } else {
            echo "   ✗ {$method}() not found in repository\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Repository test failed: " . $e->getMessage() . "\n";
}

echo "\n10. Verifying Middleware Registration...\n";
$middleware = app('router')->getMiddleware();
if (isset($middleware['admin'])) {
    echo "   ✓ Admin middleware registered\n";
} else {
    echo "   ✗ Admin middleware not registered\n";
}

echo "\n=== Verification Complete ===\n";
echo "\nAPI Endpoints:\n";
echo "Customer:\n";
echo "  GET /api/orders - List customer orders\n";
echo "  GET /api/orders/{order} - View customer order\n";
echo "\nAdmin:\n";
echo "  GET /api/admin/orders - List all orders\n";
echo "  GET /api/admin/orders/{order} - View order details\n";
echo "\nAdmin Filter Options:\n";
echo "  customer_id, product_id, payment_status, status, date_from, date_to, search\n";
echo "\nSee ORDER_HISTORY_MODULE.md for complete documentation.\n";