<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidTransferException extends \Exception
{
    public function __construct(string $message = 'The wallet transfer could not be completed.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 409);
    }
}
