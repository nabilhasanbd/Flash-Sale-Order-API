<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'type' => $this->type?->value,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'balance_before' => number_format((float) $this->balance_before, 2, '.', ''),
            'balance_after' => number_format((float) $this->balance_after, 2, '.', ''),
            'reference' => $this->reference,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
