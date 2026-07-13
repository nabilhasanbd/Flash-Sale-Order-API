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
}
