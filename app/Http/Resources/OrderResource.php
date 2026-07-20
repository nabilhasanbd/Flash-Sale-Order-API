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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'payment_status' => $this->payment_status->value,
            'status' => $this->status->value,
            'payment' => $this->whenLoaded('paymentTransaction', function () {
                $debit = $this->paymentTransaction->debitEntry();

                return [
                    'payment_reference' => $this->paymentTransaction->reference,
                    'payment_status' => $this->paymentTransaction->status->value,
                    'amount' => number_format((float) $this->paymentTransaction->amount, 2, '.', ''),
                    'payment_method' => $this->paymentTransaction->payment_method?->value,
                    'ledger_reference' => $debit?->reference,
                ];
            }),
            'coupon' => $this->whenLoaded('coupon', fn () => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'discount_applied' => (string) $this->discount,
            ] : null),
            'items' => $this->whenLoaded('orderItems', fn () => $this->orderItems->map(fn ($item) => [
                'id' => $item->id,
                'product' => $this->nestedProduct($item),
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
            ])->toArray()),
            'total_quantity' => $this->whenLoaded('orderItems', fn () => $this->orderItems->sum('quantity')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function nestedProduct($item): ?array
    {
        if (! $item->relationLoaded('product') || $item->product === null) {
            return null;
        }

        return [
            'id' => $item->product->id,
            'name' => $item->product->name,
            'description' => $item->product->description,
        ];
    }
}
