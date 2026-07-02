<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use BadMethodCallException;

/**
 * Single interface for stock reads (and later writes) per branch/outlet.
 *
 * Current implementation reads tenant-global stock (ingredients.current_stock).
 * After merge with multi-outlet branch: swap body to outlet_inventory + central fallback.
 */
class BranchStockService
{
    public function getIngredientStock(int $ingredientId, ?int $branchId = null): float
    {
        $ingredient = Ingredient::query()->find($ingredientId);

        if (! $ingredient) {
            return 0.0;
        }

        return (float) $ingredient->current_stock;
    }

    public function getFinishedGoodsStock(Product $product, ?int $branchId = null): float
    {
        $finishedGoodsName = "{$product->name} ( Produk Jadi )";
        $finishedGoods = Ingredient::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('name', $finishedGoodsName)
            ->first();

        if (! $finishedGoods) {
            return 0.0;
        }

        return $this->getIngredientStock($finishedGoods->id, $branchId);
    }

    /**
     * Deduct stock for a POS sale at a branch. Implemented after multi-outlet merge.
     */
    public function deductForSale(/* Sale $sale */): void
    {
        throw new BadMethodCallException('Branch-scoped deduct belum diimplementasi — merge dengan branch multi.');
    }

    /**
     * Reverse stock for a voided POS sale. Implemented after multi-outlet merge.
     */
    public function reverseForSale(/* Sale $sale */): void
    {
        throw new BadMethodCallException('Branch-scoped reverse belum diimplementasi — merge dengan branch multi.');
    }
}
