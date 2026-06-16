<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\MarginService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarginController extends Controller
{
    public function index(MarginService $margin): Response
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('recipeItems.ingredient.priceHistories')
            ->orderBy('name')
            ->get();

        $rows = $products->map(function (Product $product) use ($margin) {
            $info = $margin->productMargin($product);
            $info['price_history'] = $margin->ingredientPriceHistory($product);

            return $info;
        })->values();

        return Inertia::render('Margin/Index', [
            'products' => $rows,
            'alerts' => $margin->alerts(),
            'whatIf' => null,
        ]);
    }

    public function whatIf(Request $request, MarginService $margin): Response
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'increase_percent' => ['required', 'numeric', 'gte:0'],
        ]);

        $product = Product::with('recipeItems.ingredient')->findOrFail($data['product_id']);
        $result = $margin->whatIf($product, (float) $data['increase_percent']);
        $result['product'] = $product->name;
        $result['product_id'] = $product->id;

        $products = Product::query()
            ->where('is_active', true)
            ->with('recipeItems.ingredient.priceHistories')
            ->orderBy('name')
            ->get()
            ->map(function (Product $p) use ($margin) {
                $info = $margin->productMargin($p);
                $info['price_history'] = $margin->ingredientPriceHistory($p);

                return $info;
            })->values();

        return Inertia::render('Margin/Index', [
            'products' => $products,
            'alerts' => $margin->alerts(),
            'whatIf' => $result,
        ]);
    }
}
