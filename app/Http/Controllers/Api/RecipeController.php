<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecipeRequest;
use App\Http\Support\ApiResponse;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    /**
     * Get recipe for a product.
     */
    public function show(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $items = RecipeItem::where('product_id', $productId)
            ->with('ingredient:id,name,base_unit')
            ->get()
            ->map(fn (RecipeItem $item) => [
                'id' => $item->id,
                'ingredient_id' => $item->ingredient_id,
                'ingredient_name' => $item->ingredient?->name,
                'base_unit' => $item->ingredient?->base_unit,
                'quantity' => (float) $item->quantity,
            ]);

        return ApiResponse::success('Resep produk.', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_unit' => $product->unit,
            'recipe_type' => $product->recipe_type,
            'items' => $items->values()->all(),
        ]);
    }

    /**
     * Create or replace recipe for a product.
     */
    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $productId = $request->integer('product_id');
        $items = $request->input('items', []);

        DB::transaction(function () use ($productId, $items) {
            // Delete existing recipe
            RecipeItem::where('product_id', $productId)->delete();

            // Create new items
            foreach ($items as $item) {
                RecipeItem::create([
                    'product_id' => $productId,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'tenant_id' => $this->getTenantId(),
                ]);
            }
        });

        $product = Product::findOrFail($productId);
        $savedItems = RecipeItem::where('product_id', $productId)
            ->with('ingredient:id,name,base_unit')
            ->get()
            ->map(fn (RecipeItem $item) => [
                'ingredient_id' => $item->ingredient_id,
                'ingredient_name' => $item->ingredient?->name,
                'quantity' => (float) $item->quantity,
                'base_unit' => $item->ingredient?->base_unit,
            ]);

        return ApiResponse::success('Resep berhasil disimpan.', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'items' => $savedItems->values()->all(),
        ], 201);
    }

    private function getTenantId(): int
    {
        return request()->user()->tenant_id;
    }
}
