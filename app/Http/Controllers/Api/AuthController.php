<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'token' => $result['token'],
            'data' => new UserResource($result['user']),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $result['token'],
            'data' => new UserResource($result['user']),
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(message: 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->resourceResponse(
            new UserResource($request->user()),
            'User profile retrieved successfully.',
        );
    }
}
