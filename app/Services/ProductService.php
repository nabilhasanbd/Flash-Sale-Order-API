<?php

namespace App\Services;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
    ) {}

    public function createProduct(array $data): Product
    {
        return $this->productRepository->create($data);
    }

    public function getProducts(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->productRepository->paginateWithFilters($search, $status, $perPage);
    }

    public function getProduct(int $id): ?Product
    {
        return $this->productRepository->findProduct($id);
    }

    public function updateProduct(int $id, array $data): ?Product
    {
        $product = $this->productRepository->findProduct($id);

        if ($product === null) {
            return null;
        }

        $this->productRepository->update($id, $data);

        return $this->productRepository->findProduct($id);
    }

    public function deleteProduct(int $id): bool
    {
        return $this->productRepository->delete($id);
    }
}
