<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $product)
    {
        parent::__construct($product);
    }

    public function paginateWithFilters(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->search($search)
            ->status($status)
            ->newest()
            ->paginate($perPage);
    }

    public function findProduct(int $id): ?Product
    {
        return $this->model->newQuery()->find($id);
    }

    public function getAvailableProducts(?string $search, ?float $minPrice, ?float $maxPrice, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->active()
            ->flashSaleRunning()
            ->inStock()
            ->search($search)
            ->priceBetween($minPrice, $maxPrice)
            ->newest()
            ->paginate($perPage);
    }

    public function getAvailableProductById(int $id): ?Product
    {
        return $this->model->newQuery()
            ->active()
            ->flashSaleRunning()
            ->inStock()
            ->find($id);
    }

    public function findProductByIdForUpdate(int $id): ?Product
    {
        return $this->model->newQuery()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }
}
