<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getProducts(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: (int) $request->query('per_page', 15),
        );

        return $this->collectionResponse(
            ProductResource::collection($products),
            'Products retrieved successfully.',
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->createProduct($request->validated());

        return $this->resourceResponse(
            new ProductResource($product),
            'Product created successfully.',
            Response::HTTP_CREATED,
        );
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->resourceResponse(
            new ProductResource($product),
            'Product retrieved successfully.',
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updatedProduct = $this->productService->updateProduct($product->id, $request->validated());

        if ($updatedProduct === null) {
            return $this->errorResponse('Product not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            new ProductResource($updatedProduct),
            'Product updated successfully.',
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $deleted = $this->productService->deleteProduct($product->id);

        if (! $deleted) {
            return $this->errorResponse('Product not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(message: 'Product deleted successfully.');
    }
}
