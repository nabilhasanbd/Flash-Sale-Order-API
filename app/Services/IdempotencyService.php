<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * @param  array<string, mixed> $payload
     * @return array{0: Order, 1: bool}
     */
    public function execute(User $user, string $key, array $payload, \Closure $action): array
    {
        $existing = IdempotencyKey::where('idempotency_key', $key)->first();

        if ($existing !== null) {
            if ($existing->user_id !== $user->id) {
                throw new IdempotencyConflictException(
                    'This idempotency key is already in use by another account.'
                );
            }

            if ($existing->request_hash !== null && $existing->request_hash !== $this->hashPayload($payload)) {
                throw new IdempotencyConflictException(
                    'The idempotency key was used with a different request body.'
                );
            }

            if ($existing->order_id !== null) {
                return [$existing->order, true];
            }

            throw new IdempotencyConflictException(
                'A request with this idempotency key is already being processed.'
            );
        }

        try {
            $record = IdempotencyKey::create([
                'user_id' => $user->id,
                'idempotency_key' => $key,
                'request_hash' => $this->hashPayload($payload),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new IdempotencyConflictException(
                'A request with this idempotency key is already being processed.'
            );
        }

        try {
            $order = $action();

            $record->update(['order_id' => $order->id]);

            return [$order, false];
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, string>
     */
    private function hashPayload(array $payload): array
    {
        $normalized = $payload;
        ksort($normalized);

        return ['hash' => hash('sha256', (string) json_encode($normalized))];
    }
}
