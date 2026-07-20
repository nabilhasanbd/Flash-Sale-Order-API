<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class SelfTransferException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'A wallet cannot transfer funds to itself.',
        ], 422);
    }
}
