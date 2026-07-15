<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        protected OrderHistoryService $orderHistoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $result = $this->orderHistoryService->adminIndex($filters);

        return response()->json($result, 200);
    }

    public function show(int $order): JsonResponse
    {
        $result = $this->orderHistoryService->adminShow($order);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result, 200);
    }

    protected function validateFilters(Request $request): array
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'payment_status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'failed'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'completed', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return $filters;
    }
}