<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Support\ApiResponse;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'selling_price', 'recipe_type', 'estimated_yield_per_batch'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'selling_price' => (float) $product->selling_price,
                'recipe_type' => $product->recipe_type,
                'estimated_yield_per_batch' => $product->estimated_yield_per_batch,
            ]);

        return ApiResponse::success('Daftar produk.', $products->values()->all());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::create([
            ...$request->validated(),
            'is_active' => true,
            'tenant_id' => $tenantId,
        ]);

        return ApiResponse::success('Produk berhasil dibuat.', [
            'id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'selling_price' => (float) $product->selling_price,
            'recipe_type' => $product->recipe_type,
        ], 201);
    }
}
