<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'available_stock',
        'flash_sale_start',
        'flash_sale_end',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'available_stock' => 'integer',
        'flash_sale_start' => 'datetime',
        'flash_sale_end' => 'datetime',
        'status' => ProductStatus::class,
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
