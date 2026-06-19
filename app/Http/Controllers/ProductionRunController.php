<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\ProductionRun;
use App\Models\Product;
use App\Services\CogsService;
use App\Services\ProductionRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductionRunController extends Controller
{
    public function index(): Response
    {
        $runs = ProductionRun::query()
            ->with('product:id,name,unit')
            ->latest('produced_at')
            ->limit(50)
            ->get()
            ->map(fn (ProductionRun $run) => [
                'id' => $run->id,
                'product' => $run->product?->name,
                'product_id' => $run->product_id,
                'batch_count' => $run->batch_count,
                'yield_actual' => $run->yield_actual,
                'waste_count' => $run->waste_count,
                'total_cost' => (float) $run->total_cost,
                'cost_per_unit' => $run->getCostPerUnit(),
                'yield_per_batch' => $run->getYieldPerBatch(),
                'waste_percentage' => round($run->getWastePercentage(), 1),
                'notes' => $run->notes,
                'produced_at' => $run->produced_at?->toIso8601String(),
            ]);

        // Batch products with their recipes
        $batchProducts = Product::query()
            ->where('recipe_type', 'batch')
            ->with(['recipeItems.ingredient' => function ($q) {
                $q->where('item_type', 'raw_material');
            }])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'unit' => $p->unit,
                'recipe' => $p->recipeItems->map(fn ($ri) => [
                    'ingredient_id' => $ri->ingredient_id,
                    'ingredient' => $ri->ingredient?->name,
                    'base_unit' => $ri->ingredient?->base_unit,
                    'quantity' => (float) $ri->quantity,
                    'unit_price' => (float) ($ri->ingredient?->unit_price ?? 0),
                ])->values(),
            ]);

        return Inertia::render('ProductionRuns/Index', [
            'runs' => $runs,
            'batchProducts' => $batchProducts,
        ]);
    }

    public function store(Request $request, ProductionRunService $service): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_count' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.quantity_used' => ['required', 'numeric', 'gt:0'],
            'yield_actual' => ['required', 'integer', 'gt:0'],
            'waste_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $service->create(
                product: $product,
                batchCount: $validated['batch_count'],
                items: $validated['items'],
                yieldActual: $validated['yield_actual'],
                wasteCount: $validated['waste_count'] ?? 0,
                notes: $validated['notes'] ?? null,
            );

            return back()->with('success', 'Produksi tercatat.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['batch_count' => $e->getMessage()]);
        }
    }

    public function destroy(ProductionRun $productionRun, ProductionRunService $service): RedirectResponse
    {
        try {
            $service->reverse($productionRun);

            return back()->with('success', 'Produksi dibatalkan. Stok dikembalikan.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
