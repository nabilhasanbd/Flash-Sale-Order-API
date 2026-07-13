<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

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

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where('name', 'ilike', '%'.$search.'%');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value);
    }

    public function scopeInFlashSale(Builder $query): Builder
    {
        return $query->where('flash_sale_start', '<=', now())
            ->where('flash_sale_end', '>=', now());
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available_stock', '>', 0);
    }

    public function scopePriceRange(Builder $query, ?float $minPrice, ?float $maxPrice): Builder
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }
}
