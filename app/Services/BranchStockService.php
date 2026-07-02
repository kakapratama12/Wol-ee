<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\OutletInventory;
use App\Models\Product;

/**
 * Single interface for stock reads per branch/outlet.
 *
 * When branchId is provided: reads from outlet_inventory.
 * When branchId is null: falls back to central stock (ingredients.current_stock).
 */
class BranchStockService
{
    public function getIngredientStock(int $ingredientId, ?int $branchId = null): float
    {
        // If branchId provided, only read from outlet_inventory (no fallback)
        if ($branchId !== null) {
            $inventory = OutletInventory::query()
                ->where('outlet_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->whereNull('product_id')
                ->first();

            return $inventory ? (float) $inventory->quantity : 0.0;
        }

        // No branchId = central stock
        $ingredient = Ingredient::query()->find($ingredientId);

        if (! $ingredient) {
            return 0.0;
        }

        return (float) $ingredient->current_stock;
    }

    public function getFinishedGoodsStock(Product $product, ?int $branchId = null): float
    {
        // If branchId provided, only read from outlet_inventory (no fallback)
        if ($branchId !== null) {
            $inventory = OutletInventory::query()
                ->where('outlet_id', $branchId)
                ->where('product_id', $product->id)
                ->whereNull('ingredient_id')
                ->first();

            return $inventory ? (float) $inventory->quantity : 0.0;
        }

        // No branchId = central stock
        $finishedGoodsName = "{$product->name} ( Produk Jadi )";
        $finishedGoods = Ingredient::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('name', $finishedGoodsName)
            ->first();

        if (! $finishedGoods) {
            return 0.0;
        }

        return $this->getIngredientStock($finishedGoods->id, null);
    }

    /**
     * Deduct stock for a POS sale at an outlet.
     * Allows negative stock (no hard stop) — flexible first, audit later.
     */
    public function deductForSale(int $outletId, int $ingredientId, float $quantity, ?string $note = null): void
    {
        $inventory = OutletInventory::firstOrCreate(
            [
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'product_id' => null,
            ],
            [
                'quantity' => 0,
                'unit' => 'unit',
            ]
        );

        $inventory->subtractQuantity($quantity);
    }

    /**
     * Reverse stock deduction for a voided POS sale.
     */
    public function reverseForSale(int $outletId, int $ingredientId, float $quantity): void
    {
        $inventory = OutletInventory::where([
            'outlet_id' => $outletId,
            'ingredient_id' => $ingredientId,
            'product_id' => null,
        ])->first();

        if ($inventory) {
            $inventory->addQuantity($quantity);
        }
    }

    /**
     * Deduct finished goods stock for batch product sales at outlet.
     * Allows negative stock (no hard stop).
     */
    public function deductFinishedGoodsForSale(int $outletId, Product $product, float $quantity): void
    {
        $inventory = OutletInventory::firstOrCreate(
            [
                'outlet_id' => $outletId,
                'product_id' => $product->id,
                'ingredient_id' => null,
            ],
            [
                'quantity' => 0,
                'unit' => $product->unit,
            ]
        );

        $inventory->subtractQuantity($quantity);
    }

    /**
     * Reverse finished goods stock for voided sales at outlet.
     */
    public function reverseFinishedGoodsForSale(int $outletId, Product $product, float $quantity): void
    {
        $inventory = OutletInventory::where([
            'outlet_id' => $outletId,
            'product_id' => $product->id,
            'ingredient_id' => null,
        ])->first();

        if ($inventory) {
            $inventory->addQuantity($quantity);
        }
    }
}
