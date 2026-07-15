<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderHistoryService $orderHistoryService,
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
}