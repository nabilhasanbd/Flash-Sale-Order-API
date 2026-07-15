<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}