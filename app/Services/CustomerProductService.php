<?php

namespace App\Services;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
    ) {}

    public function getProducts(?string $search, ?string $minPrice, ?string $maxPrice, int $perPage = 15): LengthAwarePaginator
    {
        $minPrice = $minPrice !== null ? (float) $minPrice : null;
        $maxPrice = $maxPrice !== null ? (float) $maxPrice : null;

        return $this->productRepository->getAvailableProducts($search, $minPrice, $maxPrice, $perPage);
    }

    public function getProduct(int $id): ?Product
    {
        return $this->productRepository->getAvailableProductById($id);
    }
}