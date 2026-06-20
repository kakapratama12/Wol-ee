<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\ProductionRun;
use App\Models\ProductionRunItem;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionRunService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CogsService $cogs,
    ) {}

    /**
     * Create a production run: auto-use recipe quantities × batchCount.
     * Deduct raw materials, no user input needed for ingredients.
     * Yield is recorded later via updateYield().
     * Atomic — all or nothing.
     */
    public function create(
        Product $product,
        int $batchCount,
        ?string $notes = null,
        ?CarbonInterface $producedAt = null,
    ): ProductionRun {
        // Validate: product must be batch type
        if (! $product->isBatch()) {
            throw new InvalidArgumentException(
                "Produk \"{$product->name}\" bukan tipe batch. Gunakan production run untuk tipe batch saja."
            );
        }

        // Load recipe items with ingredients
        $product->loadMissing('recipeItems.ingredient');

        if ($product->recipeItems->isEmpty()) {
            throw new InvalidArgumentException(
                "Produk \"{$product->name}\" belum memiliki resep. Tambahkan resep terlebih dulu."
            );
        }

        // Build items from recipe quantities × batchCount
        $items = $product->recipeItems->map(function ($recipeItem) use ($batchCount) {
            $ingredient = $recipeItem->ingredient;
            if (! $ingredient) {
                throw new InvalidArgumentException(
                    "Bahan resep ID {$recipeItem->ingredient_id} tidak ditemukan."
                );
            }
            return [
                'ingredient_id' => $recipeItem->ingredient_id,
                'quantity_used' => (float) $recipeItem->quantity * $batchCount,
            ];
        })->toArray();

        // Validate: stock sufficient for all items
        foreach ($items as $item) {
            $ingredient = Ingredient::find($item['ingredient_id']);
            $ingredient->refresh();
            if ((float) $ingredient->current_stock < (float) $item['quantity_used']) {
                throw new InvalidArgumentException(
                    "Stok \"{$ingredient->name}\" tidak cukup. " .
                    "Tersedia: {$ingredient->current_stock} {$ingredient->base_unit}, " .
                    "diperlukan: {$item['quantity_used']} {$ingredient->base_unit}."
                );
            }
        }

        return DB::transaction(function () use (
            $product, $batchCount, $items, $notes, $producedAt
        ) {
            $producedAt = $producedAt ?? Carbon::now();

            // 1. Create production run (yield_actual = 0, waste_count = 0)
            $totalCost = 0;
            $productionRun = ProductionRun::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'batch_count' => $batchCount,
                'yield_actual' => 0,
                'waste_count' => 0,
                'total_cost' => 0, // Will update after calculating
                'notes' => $notes,
                'produced_at' => $producedAt,
            ]);

            // 2. Process each ingredient — deduct raw materials only
            foreach ($items as $item) {
                $ingredient = Ingredient::find($item['ingredient_id']);
                $quantityUsed = (float) $item['quantity_used'];
                $unitCostSnapshot = $this->cogs->costPrice($ingredient);

                // Create production run item
                ProductionRunItem::create([
                    'production_run_id' => $productionRun->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity_used' => $quantityUsed,
                    'unit_cost_snapshot' => $unitCostSnapshot,
                ]);

                // Deduct raw material from stock
                $this->inventory->recordUsage(
                    $ingredient,
                    $quantityUsed,
                    ProductionRun::class,
                    $productionRun->id,
                    "Production run #{$productionRun->id}",
                    $producedAt,
                );

                // Add to total cost
                $totalCost += $quantityUsed * $unitCostSnapshot;
            }

            // Update total cost
            $productionRun->update(['total_cost' => round($totalCost, 4)]);

            // No finished goods added at creation time — yield recorded later

            return $productionRun;
        });
    }

    /**
     * Update ingredient quantities for an existing production run.
     * Adjusts stock based on the difference between old and new quantities.
     * Recalculates total_cost.
     * Atomic — all or nothing.
     */
    public function updateItems(
        ProductionRun $productionRun,
        array $newItems, // [{ingredient_id, quantity_used}]
    ): ProductionRun {
        return DB::transaction(function () use ($productionRun, $newItems) {
            // Load current items with ingredients
            $productionRun->load('items.ingredient');

            // Build map of old items by ingredient_id
            $oldItemsMap = [];
            foreach ($productionRun->items as $item) {
                $oldItemsMap[$item->ingredient_id] = $item;
            }

            // Build map of new items by ingredient_id
            $newItemsMap = [];
            foreach ($newItems as $newItem) {
                $ingredientId = $newItem['ingredient_id'];
                $newQty = (float) $newItem['quantity_used'];
                $newItemsMap[$ingredientId] = $newQty;
            }

            // Process each ingredient: adjust stock for diff
            $totalCost = 0;

            // Handle items that exist in old OR new (or both)
            $allIngredientIds = array_unique(array_merge(
                array_keys($oldItemsMap),
                array_keys($newItemsMap)
            ));

            foreach ($allIngredientIds as $ingredientId) {
                $oldQty = isset($oldItemsMap[$ingredientId])
                    ? (float) $oldItemsMap[$ingredientId]->quantity_used
                    : 0.0;
                $newQty = $newItemsMap[$ingredientId] ?? 0.0;
                $diff = $newQty - $oldQty;

                if (abs($diff) < 0.0001) {
                    // No meaningful change — just accumulate cost from existing item
                    if (isset($oldItemsMap[$ingredientId])) {
                        $item = $oldItemsMap[$ingredientId];
                        $totalCost += $item->quantity_used * $item->unit_cost_snapshot;
                    }
                    continue;
                }

                $ingredient = Ingredient::find($ingredientId);
                if (! $ingredient) {
                    throw new InvalidArgumentException(
                        "Bahan dengan ID {$ingredientId} tidak ditemukan."
                    );
                }

                $unitCostSnapshot = $this->cogs->costPrice($ingredient);

                if ($diff > 0) {
                    // Using more: deduct additional stock
                    $ingredient->refresh();
                    if ((float) $ingredient->current_stock < $diff) {
                        throw new InvalidArgumentException(
                            "Stok \"{$ingredient->name}\" tidak cukup untuk penambahan. " .
                            "Tersedia: {$ingredient->current_stock} {$ingredient->base_unit}, " .
                            "diperlukan tambahan: {$diff} {$ingredient->base_unit}."
                        );
                    }

                    $this->inventory->recordUsage(
                        $ingredient,
                        $diff,
                        ProductionRun::class,
                        $productionRun->id,
                        "Adjustment production run #{$productionRun->id}: {$oldQty} → {$newQty} {$ingredient->base_unit}",
                    );
                } else {
                    // Using less: return stock
                    $returnQty = abs($diff);
                    $ingredient->refresh();
                    $ingredient->current_stock = (float) $ingredient->current_stock + $returnQty;
                    $ingredient->save();

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'type' => StockMovement::TYPE_REVERSAL,
                        'quantity' => $returnQty,
                        'stock_after' => $ingredient->current_stock,
                        'production_run_id' => $productionRun->id,
                        'note' => "Adjustment production run #{$productionRun->id}: {$oldQty} → {$newQty} {$ingredient->base_unit}",
                        'occurred_at' => now(),
                    ]);
                }

                // Update or create production run item
                if (isset($oldItemsMap[$ingredientId])) {
                    $oldItemsMap[$ingredientId]->update([
                        'quantity_used' => $newQty,
                        'unit_cost_snapshot' => $unitCostSnapshot,
                    ]);
                } else {
                    ProductionRunItem::create([
                        'production_run_id' => $productionRun->id,
                        'ingredient_id' => $ingredientId,
                        'quantity_used' => $newQty,
                        'unit_cost_snapshot' => $unitCostSnapshot,
                    ]);
                }

                $totalCost += $newQty * $unitCostSnapshot;
            }

            // Remove items that were deleted (present in old but not in new)
            foreach ($oldItemsMap as $ingredientId => $item) {
                if (! isset($newItemsMap[$ingredientId])) {
                    // Return stock for removed item
                    $ingredient = $item->ingredient;
                    if ($ingredient) {
                        $returnQty = (float) $item->quantity_used;
                        $ingredient->refresh();
                        $ingredient->current_stock = (float) $ingredient->current_stock + $returnQty;
                        $ingredient->save();

                        StockMovement::create([
                            'ingredient_id' => $ingredient->id,
                            'type' => StockMovement::TYPE_REVERSAL,
                            'quantity' => $returnQty,
                            'stock_after' => $ingredient->current_stock,
                            'production_run_id' => $productionRun->id,
                            'note' => "Removed ingredient from production run #{$productionRun->id}: {$returnQty} {$ingredient->base_unit}",
                            'occurred_at' => now(),
                        ]);
                    }
                    $item->delete();
                }
            }

            // Update total cost
            $productionRun->update(['total_cost' => round($totalCost, 4)]);

            return $productionRun->fresh();
        });
    }

    /**
     * Update yield and waste for a production run.
     * On first-time recording (old yield == 0), ADD finished goods to stock.
     * On subsequent edits, adjust diff.
     * Yield deviation warning only triggers when yield_actual > 0.
     * Atomic — all or nothing.
     */
    public function updateYield(
        ProductionRun $productionRun,
        int $newYieldActual,
        int $newWasteCount = 0,
    ): ProductionRun {
        if ($newYieldActual <= 0) {
            throw new \InvalidArgumentException('Yield aktual harus lebih dari 0.');
        }

        return DB::transaction(function () use ($productionRun, $newYieldActual, $newWasteCount) {
            $oldYield = $productionRun->yield_actual ?? 0;
            $oldWaste = $productionRun->waste_count ?? 0;
            $isFirstTimeRecording = ($oldYield === 0 && $oldWaste === 0);

            // Yield deviation warning (only when yield_actual > 0)
            if ($newYieldActual > 0) {
                $product = $productionRun->product;
                $expectedYield = $product->estimated_yield_per_batch
                    ? $productionRun->batch_count * $product->estimated_yield_per_batch
                    : $productionRun->batch_count * 20;
                $deviation = abs($newYieldActual - $expectedYield) / $expectedYield * 100;
                if ($deviation > 20) {
                    \Log::warning("Yield deviation > 20% for production run", [
                        'production_run_id' => $productionRun->id,
                        'product_id' => $product->id,
                        'batch_count' => $productionRun->batch_count,
                        'expected_yield' => $expectedYield,
                        'actual_yield' => $newYieldActual,
                        'deviation' => round($deviation, 2) . '%',
                    ]);
                }
            }

            // Update the production run
            $productionRun->update([
                'yield_actual' => $newYieldActual,
                'waste_count' => $newWasteCount,
            ]);

            // Adjust finished goods stock
            $finishedGoods = $this->getOrCreateFinishedGoods($productionRun->product);
            $finishedGoods->refresh();
            $oldStock = (float) $finishedGoods->current_stock;

            if ($isFirstTimeRecording) {
                // First time: ADD yield to stock, then subtract waste
                $newStock = $oldStock + $newYieldActual - $newWasteCount;
                $finishedGoods->current_stock = $newStock;
                $finishedGoods->save();

                // Record production output
                StockMovement::create([
                    'ingredient_id' => $finishedGoods->id,
                    'type' => StockMovement::TYPE_PRODUCTION_OUTPUT,
                    'quantity' => $newYieldActual,
                    'stock_after' => $newStock,
                    'production_run_id' => $productionRun->id,
                    'note' => "Production run #{$productionRun->id}: {$newYieldActual} {$finishedGoods->base_unit}",
                    'occurred_at' => now(),
                ]);

                // Record waste if any
                if ($newWasteCount > 0) {
                    $wasteStock = (float) $finishedGoods->current_stock;
                    $newWasteStock = $wasteStock - $newWasteCount;
                    $finishedGoods->current_stock = $newWasteStock;
                    $finishedGoods->save();

                    StockMovement::create([
                        'ingredient_id' => $finishedGoods->id,
                        'type' => StockMovement::TYPE_WASTE,
                        'quantity' => -$newWasteCount,
                        'stock_after' => $newWasteStock,
                        'production_run_id' => $productionRun->id,
                        'note' => "Production run #{$productionRun->id}: {$newWasteCount} {$finishedGoods->base_unit} waste",
                        'occurred_at' => now(),
                    ]);
                }
            } else {
                // Subsequent edit: adjust diff
                $yieldDiff = $newYieldActual - $oldYield;
                $wasteDiff = $newWasteCount - $oldWaste;
                $newStock = $oldStock + $yieldDiff - $wasteDiff;
                $finishedGoods->current_stock = $newStock;
                $finishedGoods->save();

                $totalDiff = $yieldDiff - $wasteDiff;
                if ($totalDiff != 0) {
                    StockMovement::create([
                        'ingredient_id' => $finishedGoods->id,
                        'type' => 'adjustment',
                        'quantity' => $totalDiff,
                        'stock_after' => $newStock,
                        'production_run_id' => $productionRun->id,
                        'note' => "Adjustment production run #{$productionRun->id}: yield {$oldYield}→{$newYieldActual}, waste {$oldWaste}→{$newWasteCount}",
                        'occurred_at' => now(),
                    ]);
                }
            }

            return $productionRun->fresh();
        });
    }

    /**
     * Reverse a production run: restore raw materials, remove finished goods.
     */
    public function reverse(ProductionRun $productionRun): void
    {
        DB::transaction(function () use ($productionRun) {
            // 1. Restore raw materials
            foreach ($productionRun->items as $item) {
                $ingredient = $item->ingredient;
                if (! $ingredient) {
                    continue;
                }

                $ingredient->refresh();
                $ingredient->current_stock = (float) $ingredient->current_stock + (float) $item->quantity_used;
                $ingredient->save();

                // Record reversal movement
                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => StockMovement::TYPE_REVERSAL,
                    'quantity' => $item->quantity_used,
                    'stock_after' => $ingredient->current_stock,
                    'production_run_id' => $productionRun->id,
                    'note' => "Reversal production run #{$productionRun->id}",
                    'occurred_at' => Carbon::now(),
                ]);
            }

            // 2. Remove finished goods
            $finishedGoods = $this->getOrCreateFinishedGoods($productionRun->product);
            $finishedGoods->refresh();

            // Remove yield
            $yieldStock = (float) $finishedGoods->current_stock - $productionRun->yield_actual;
            $finishedGoods->current_stock = max(0, $yieldStock);
            $finishedGoods->save();

            StockMovement::create([
                'ingredient_id' => $finishedGoods->id,
                'type' => StockMovement::TYPE_REVERSAL,
                'quantity' => -$productionRun->yield_actual,
                'stock_after' => $finishedGoods->current_stock,
                'production_run_id' => $productionRun->id,
                'note' => "Reversal production run #{$productionRun->id}: {$productionRun->yield_actual} {$finishedGoods->base_unit}",
                'occurred_at' => Carbon::now(),
            ]);

            // 3. Restore waste if any
            if ($productionRun->waste_count > 0) {
                $wasteStock = (float) $finishedGoods->current_stock + $productionRun->waste_count;
                $finishedGoods->current_stock = $wasteStock;
                $finishedGoods->save();

                StockMovement::create([
                    'ingredient_id' => $finishedGoods->id,
                    'type' => StockMovement::TYPE_REVERSAL,
                    'quantity' => $productionRun->waste_count,
                    'stock_after' => $wasteStock,
                    'production_run_id' => $productionRun->id,
                    'note' => "Reversal waste production run #{$productionRun->id}",
                    'occurred_at' => Carbon::now(),
                ]);
            }

            // 4. Delete production run items and the run itself
            $productionRun->items()->delete();
            $productionRun->delete();
        });
    }

    /**
     * Get or create finished goods ingredient for a product.
     * Naming convention: "{product_name} ( Produk Jadi )"
     */
    private function getOrCreateFinishedGoods(Product $product): Ingredient
    {
        $name = "{$product->name} ( Produk Jadi )";

        $finishedGoods = Ingredient::where('name', $name)
            ->where('tenant_id', $product->tenant_id)
            ->first();

        if (! $finishedGoods) {
            $finishedGoods = Ingredient::create([
                'name' => $name,
                'item_type' => Ingredient::ITEM_FINISHED_GOODS,
                'unit_type' => 'gramasi',
                'base_unit' => $product->unit,
                'unit_price' => 0,
                'current_stock' => 0,
                'minimum_stock' => 0,
                'tenant_id' => $product->tenant_id,
            ]);
        }

        return $finishedGoods;
    }
}
