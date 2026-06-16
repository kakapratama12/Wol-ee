<?php

namespace App\Services;

use App\Models\Product;

class CogsService
{
    /**
     * COGS per 1 porsi produk = Σ (gramasi resep × harga per base_unit bahan).
     */
    public function cogsForProduct(Product $product): float
    {
        $product->loadMissing('recipeItems.ingredient');

        $total = 0.0;
        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient) {
                continue;
            }
            $total += (float) $item->quantity * (float) $item->ingredient->unit_price;
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
            $cost = round((float) $item->quantity * (float) $item->ingredient->unit_price, 2);
            $rows[] = [
                'ingredient_id' => $item->ingredient_id,
                'ingredient' => $item->ingredient->name,
                'quantity' => (float) $item->quantity,
                'base_unit' => $item->ingredient->base_unit,
                'unit_price' => (float) $item->ingredient->unit_price,
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
        if ($price <= 0) {
            return 0.0;
        }

        $cogs = $this->cogsForProduct($product);

        return round((($price - $cogs) / $price) * 100, 2);
    }

    /**
     * COGS setelah ditambah waste (susut wajar).
     */
    public function withWaste(float $cogs, float $wastePercent): float
    {
        return round($cogs * (1 + ($wastePercent / 100)), 2);
    }
}
