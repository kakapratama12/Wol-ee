<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Services\CogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(CogsService $cogs): Response
    {
        $products = Product::query()
            ->with('recipeItems.ingredient:id,name,base_unit,unit_price')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($cogs) {
                $cogsValue = $cogs->cogsForProduct($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'selling_price' => (float) $product->selling_price,
                    'recipe_type' => $product->recipe_type ?? 'unit',
                    'estimated_yield_per_batch' => $product->estimated_yield_per_batch,
                    'is_active' => $product->is_active,
                    'cogs' => $cogsValue,
                    'margin' => $cogs->margin($product),
                    'recipe' => $product->recipeItems->map(fn (RecipeItem $item) => [
                        'ingredient_id' => $item->ingredient_id,
                        'ingredient' => $item->ingredient?->name,
                        'base_unit' => $item->ingredient?->base_unit,
                        'unit_price' => (float) ($item->ingredient?->unit_price ?? 0),
                        'quantity' => (float) $item->quantity,
                        'cost' => round((float) $item->quantity * (float) ($item->ingredient?->unit_price ?? 0), 2),
                    ])->values(),
                ];
            });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'base_unit', 'unit_price']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function storeJson(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'selling_price' => (float) $product->selling_price,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', 'Produk dihapus.');
    }

    public function updateRecipe(UpdateRecipeRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];

        DB::transaction(function () use ($product, $items, $validated) {
            $product->recipeItems()->delete();

            foreach ($items as $item) {
                RecipeItem::create([
                    'product_id' => $product->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // Update estimated_yield_per_batch if provided (recipe-level setting)
            if (array_key_exists('estimated_yield_per_batch', $validated)) {
                $product->update([
                    'estimated_yield_per_batch' => $validated['estimated_yield_per_batch'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Resep disimpan & COGS diperbarui.');
    }
}
