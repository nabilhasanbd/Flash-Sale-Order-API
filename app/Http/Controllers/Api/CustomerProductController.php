<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\CustomerProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerProductController extends Controller
{
    public function __construct(
        protected CustomerProductService $customerProductService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->customerProductService->getProducts(
            search: $request->query('search'),
            minPrice: $request->query('min_price'),
            maxPrice: $request->query('max_price'),
            perPage: (int) $request->query('per_page', 15),
        );

        return $this->collectionResponse(
            ProductResource::collection($products),
            'Products retrieved successfully.',
        );
    }

    public function show(Request $request): JsonResponse
    {
        $product = $this->customerProductService->getProduct((int) $request->route('product'));

        if ($product === null) {
            return $this->errorResponse('Product is not available.', Response::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            new ProductResource($product),
            'Product retrieved successfully.',
        );
    }
}