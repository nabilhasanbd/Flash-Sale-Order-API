<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $balance = $this->walletService->getBalance($request->user());

        return $this->successResponse([
            'balance' => number_format($balance, 2, '.', ''),
        ], 'Wallet balance retrieved successfully.');
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->walletService->getStatement(
            $request->user(),
            (int) $request->query('per_page', 15),
        );

        return $this->collectionResponse(
            WalletTransactionResource::collection($transactions),
            'Wallet transactions retrieved successfully.',
        );
    }
}