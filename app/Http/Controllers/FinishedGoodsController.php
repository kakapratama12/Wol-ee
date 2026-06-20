<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductionRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinishedGoodsController extends Controller
{
    public function index(): Response
    {
        // Get all batch products with their finished goods ingredients
        $batchProducts = Product::query()
            ->where('recipe_type', 'batch')
            ->with(['productionRuns' => function ($q) {
                $q->latest('produced_at');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                // Get or find the finished goods ingredient
                $finishedGoodsName = "{$product->name} ( Produk Jadi )";
                $finishedGoods = Ingredient::where('name', $finishedGoodsName)
                    ->where('tenant_id', $product->tenant_id)
                    ->first();

                $currentStock = $finishedGoods ? (float) $finishedGoods->current_stock : 0;

                // Calculate average COGS from production runs
                $totalCost = 0;
                $totalYield = 0;
                $productionDetails = [];

                foreach ($product->productionRuns as $run) {
                    $totalCost += (float) $run->total_cost;
                    $totalYield += $run->yield_actual;

                    $productionDetails[] = [
                        'id' => $run->id,
                        'produced_at' => $run->produced_at?->toIso8601String(),
                        'yield_actual' => $run->yield_actual,
                        'waste_count' => $run->waste_count,
                        'yield_recorded' => ($run->yield_actual ?? 0) > 0,
                        'total_cost' => (float) $run->total_cost,
                        'cost_per_unit' => $run->yield_actual > 0 ? round((float) $run->total_cost / $run->yield_actual, 2) : 0,
                        'notes' => $run->notes,
                    ];
                }

                $avgCogs = $totalYield > 0 ? round($totalCost / $totalYield, 2) : 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'selling_price' => (float) $product->selling_price,
                    'current_stock' => $currentStock,
                    'avg_cogs' => $avgCogs,
                    'margin' => $product->selling_price > 0
                        ? round((($product->selling_price - $avgCogs) / $product->selling_price) * 100, 2)
                        : 0,
                    'production_count' => $product->productionRuns->count(),
                    'production_details' => $productionDetails,
                ];
            });

        return Inertia::render('FinishedGoods/Index', [
            'batchProducts' => $batchProducts,
        ]);
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $finishedGoodsName = "{$product->name} ( Produk Jadi )";
        $finishedGoods = Ingredient::where('name', $finishedGoodsName)
            ->where('tenant_id', $product->tenant_id)
            ->first();

        if (! $finishedGoods) {
            return back()->withErrors(['error' => 'Produk jadi tidak ditemukan.']);
        }

        $adjustment = (float) $validated['adjustment'];

        DB::transaction(function () use ($finishedGoods, $adjustment, $validated, $product) {
            $oldStock = (float) $finishedGoods->current_stock;
            $newStock = $oldStock + $adjustment;

            $finishedGoods->current_stock = $newStock;
            $finishedGoods->save();

            // Record adjustment movement
            \App\Models\StockMovement::create([
                'ingredient_id' => $finishedGoods->id,
                'type' => 'adjustment',
                'quantity' => $adjustment,
                'stock_after' => $newStock,
                'note' => $validated['note'] ?? "Manual adjustment: {$adjustment} {$product->unit}",
                'occurred_at' => now(),
            ]);
        });

        return back()->with('success', 'Stok produk jadi disesuaikan.');
    }
}
