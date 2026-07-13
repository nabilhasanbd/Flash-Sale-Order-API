<?php

namespace App\Services;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CustomerProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
    ) {}

    public function index(?string $search, ?string $minPrice, ?string $maxPrice, int $perPage = 15): LengthAwarePaginator
    {
        $minPrice = $minPrice !== null ? (float) $minPrice : null;
        $maxPrice = $maxPrice !== null ? (float) $maxPrice : null;

        return $this->productRepository->getAvailableProducts($search, $minPrice, $maxPrice, $perPage);
    }

    public function show(int $id): ?Product
    {
        $product = $this->productRepository->getAvailableProductById($id);

        if ($product === null) {
            return null;
        }

        if (! $product->isActive() || ! $product->isFlashSaleRunning() || ! $product->isInStock()) {
            return null;
        }

        return $product;
    }
}