<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseTrait
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    protected function errorResponse(
        string $message = 'Error',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        mixed $errors = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    protected function resourceResponse(
        JsonResource $resource,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
        ], $statusCode);
    }

    protected function collectionResponse(
        ResourceCollection $collection,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection,
        ], $statusCode);
    }
}
