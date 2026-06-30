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
            ->with(['product:id,name,unit', 'items.ingredient:id,name,base_unit'])
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
                'yield_recorded' => ($run->yield_actual ?? 0) > 0,
                'total_cost' => (float) $run->total_cost,
                'cost_per_unit' => $run->getCostPerUnit(),
                'yield_per_batch' => $run->getYieldPerBatch(),
                'waste_percentage' => round($run->getWastePercentage(), 1),
                'notes' => $run->notes,
                'produced_at' => $run->produced_at?->toIso8601String(),
                'items' => $run->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient' => $item->ingredient?->name,
                    'base_unit' => $item->ingredient?->base_unit,
                    'quantity_used' => (float) $item->quantity_used,
                    'unit_cost_snapshot' => (float) $item->unit_cost_snapshot,
                ])->values(),
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
                'estimated_yield_per_batch' => $p->estimated_yield_per_batch,
                'recipe' => $p->recipeItems->map(fn ($ri) => [
                    'ingredient_id' => $ri->ingredient_id,
                    'ingredient' => $ri->ingredient?->name,
                    'base_unit' => $ri->ingredient?->base_unit,
                    'quantity' => (float) $ri->quantity,
                    'unit_price' => (float) ($ri->ingredient?->unit_price ?? 0),
                ])->values(),
            ]);

        // All ingredients for "Tambah Bahan" dropdown
        $ingredients = Ingredient::query()
            ->orderBy('name')
            ->get(['id', 'name', 'base_unit', 'unit_price', 'current_stock']);

        return Inertia::render('ProductionRuns/Index', [
            'runs' => $runs,
            'batchProducts' => $batchProducts,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request, ProductionRunService $service): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_count' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $service->create(
                product: $product,
                batchCount: $validated['batch_count'],
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

    public function updateYield(Request $request, ProductionRun $productionRun, ProductionRunService $service): RedirectResponse
    {
        $validated = $request->validate([
            'yield_actual' => ['required', 'integer', 'gt:0'],
            'waste_count' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $service->updateYield(
                productionRun: $productionRun,
                newYieldActual: $validated['yield_actual'],
                newWasteCount: $validated['waste_count'] ?? 0,
            );

            return back()->with('success', 'Yield diperbarui & stok disesuaikan.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['yield_actual' => $e->getMessage()]);
        }
    }

    /**
     * Update ingredient quantities for a production run.
     * Adjusts stock based on the diff and recalculates total_cost.
     */
    public function updateItems(Request $request, ProductionRun $productionRun, ProductionRunService $service): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.quantity_used' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $service->updateItems(
                productionRun: $productionRun,
                newItems: $validated['items'],
            );

            return back()->with('success', 'Bahan produksi diperbarui & stok disesuaikan.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }
    }
}
