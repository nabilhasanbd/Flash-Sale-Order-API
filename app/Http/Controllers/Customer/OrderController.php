<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\IdempotencyService;
use App\Services\OrderHistoryService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderHistoryService $orderHistoryService,
        protected OrderService $orderService,
        protected IdempotencyService $idempotencyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->orderHistoryService->index($request->user());

        return response()->json($result, 200);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $result = $this->orderHistoryService->show($request->user(), $order);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result, 200);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        $payload = $request->only(['product_id', 'quantity', 'coupon_code']);
        $couponCode = $request->filled('coupon_code')
            ? $request->string('coupon_code')->toString()
            : null;

        [$order, $replayed] = $this->idempotencyService->execute(
            $request->user(),
            $key,
            $payload,
            fn () => $this->orderService->placeOrder(
                $request->user(),
                $request->integer('product_id'),
                $request->integer('quantity'),
                $couponCode,
            ),
        );

        $order->load('orderItems.product');

        if ($replayed) {
            return $this->resourceResponse(
                new OrderResource($order),
                'Order already processed.',
            );
        }

        return $this->resourceResponse(
            new OrderResource($order),
            'Order placed successfully.',
            Response::HTTP_CREATED,
        );
    }
}