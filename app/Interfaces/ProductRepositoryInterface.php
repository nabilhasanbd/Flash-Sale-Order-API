<?php

namespace App\Interfaces;

use App\Models\Product;
use App\Repositories\BaseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator;

    public function findProduct(int $id): ?Product;

    public function getAvailableProducts(?string $search, ?float $minPrice, ?float $maxPrice, int $perPage = 15): LengthAwarePaginator;

    public function getAvailableProductById(int $id): ?Product;

    public function expireFinishedFlashSales(): int;
}
