<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class IdempotencyConflictException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 409);
    }
}
