<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] ?? null,
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'payment_status' => $this->payment_status->value,
            'status' => $this->status->value,
            'coupon' => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'discount_applied' => (string) $this->discount,
            ] : null,
            'items' => $this->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'description' => $item->product->description,
                    ] ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'subtotal' => (string) $item->subtotal,
                ];
            })->toArray(),
            'total_quantity' => $this->orderItems->sum('quantity'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}