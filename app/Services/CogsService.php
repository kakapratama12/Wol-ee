<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Support\CalculationHelper;

class CogsService
{
    /**
     * Harga per base_unit untuk perhitungan COGS (weighted average).
     */
    public function costPrice(Ingredient $ingredient): float
    {
        $weighted = (float) $ingredient->weighted_avg_price;

        return $weighted > 0 ? $weighted : (float) $ingredient->unit_price;
    }

    /**
     * COGS per 1 porsi produk = Σ (gramasi resep × harga per base_unit bahan).
     * For batch products: divide by estimated_yield_per_batch to get per-unit COGS.
     */
    public function cogsForProduct(Product $product): float
    {
        $product->loadMissing('recipeItems.ingredient');

        $total = 0.0;
        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient) {
                continue;
            }
            $total += (float) $item->quantity * $this->costPrice($item->ingredient);
        }

        // For batch products, divide by estimated yield to get per-unit COGS
        if ($product->isBatch() && $product->estimated_yield_per_batch > 0) {
            $total = $total / $product->estimated_yield_per_batch;
        }

        return round($total, 2);
    }

    /**
     * Rincian COGS per bahan untuk ditampilkan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function breakdown(Product $product): array
    {
        $product->loadMissing('recipeItems.ingredient');

        $rows = [];
        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient) {
                continue;
            }
            $costPrice = $this->costPrice($item->ingredient);
            $cost = round((float) $item->quantity * $costPrice, 2);
            $rows[] = [
                'ingredient_id' => $item->ingredient_id,
                'ingredient' => $item->ingredient->name,
                'quantity' => (float) $item->quantity,
                'base_unit' => $item->ingredient->base_unit,
                'unit_price' => $costPrice,
                'cost' => $cost,
            ];
        }

        return $rows;
    }

    /**
     * Margin (%) berdasarkan harga jual produk.
     */
    public function margin(Product $product): float
    {
        $price = (float) $product->selling_price;
        $cogs = $this->cogsForProduct($product);

        return CalculationHelper::marginPercent($price, $cogs);
    }

    /**
     * COGS setelah ditambah waste (susut wajar).
     */
    public function withWaste(float $cogs, float $wastePercent): float
    {
        return round($cogs * (1 + ($wastePercent / 100)), 2);
    }

    /**
     * Average COGS for batch products from production runs in the same
     * month as the sale. Returns total cost / total yield for that period.
     */
    public function averageCogsForBatchProduct(Product $product, ?\Carbon\CarbonInterface $occurredAt = null): float
    {
        $product->loadMissing('productionRuns');

        $runs = $product->productionRuns;

        // Filter by month if date provided
        if ($occurredAt) {
            $runs = $runs->filter(function ($run) use ($occurredAt) {
                return $run->created_at->month === $occurredAt->month
                    && $run->created_at->year === $occurredAt->year;
            });
        }

        $totalCost = 0;
        $totalYield = 0;

        foreach ($runs as $run) {
            $totalCost += (float) $run->total_cost;
            $totalYield += $run->yield_actual;
        }

        if ($totalYield === 0) {
            // Fallback to recipe-based COGS if no production runs
            return $this->cogsForProduct($product);
        }

        return round($totalCost / $totalYield, 2);
    }
}
