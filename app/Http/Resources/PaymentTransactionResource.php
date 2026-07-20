<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'order_id' => $this->order_id,
            'customer_wallet_id' => $this->customer_wallet_id,
            'merchant_wallet_id' => $this->merchant_wallet_id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'payment_method' => $this->payment_method?->value,
            'transaction_type' => $this->transaction_type?->value,
            'status' => $this->status?->value,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'wallet_transactions' => WalletTransactionResource::collection(
                $this->whenLoaded('walletTransactions')
            ),
        ];
    }
}
