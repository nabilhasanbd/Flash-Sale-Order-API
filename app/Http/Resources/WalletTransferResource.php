<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_wallet' => new WalletResource($this->resource['customer_wallet']),
            'merchant_wallet' => new WalletResource($this->resource['merchant_wallet']),
            'debit' => new WalletTransactionResource($this->resource['debit']),
            'credit' => new WalletTransactionResource($this->resource['credit']),
        ];
    }
}
