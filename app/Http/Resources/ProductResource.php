<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'available_stock' => $this->available_stock,
            'flash_sale_start' => $this->flash_sale_start?->format('Y-m-d H:i:s'),
            'flash_sale_end' => $this->flash_sale_end?->format('Y-m-d H:i:s'),
            'status' => $this->status->value,
        ];
    }
}
