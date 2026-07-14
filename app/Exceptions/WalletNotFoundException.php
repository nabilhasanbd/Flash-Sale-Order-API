<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class WalletNotFoundException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Wallet not found.',
        ], 404);
    }
}