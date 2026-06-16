<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly CogsService $cogs,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Catat penjualan: hitung COGS snapshot, simpan sale, kurangi stok bahan
     * sesuai resep. Atomic.
     */
    public function record(
        Product $product,
        int $quantity,
        ?float $unitPrice = null,
        string $source = 'web',
        ?int $userId = null,
        ?string $note = null,
        ?CarbonInterface $occurredAt = null,
    ): Sale {
        return DB::transaction(function () use ($product, $quantity, $unitPrice, $source, $userId, $note, $occurredAt) {
            $product->loadMissing('recipeItems.ingredient');

            $occurredAt = $occurredAt ?? Carbon::now();
            $unitPrice = $unitPrice ?? (float) $product->selling_price;

            $cogsPerUnit = $this->cogs->cogsForProduct($product);
            $revenue = round($unitPrice * $quantity, 2);
            $cogsTotal = round($cogsPerUnit * $quantity, 2);
            $profit = round($revenue - $cogsTotal, 2);
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

            $sale = Sale::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'revenue' => $revenue,
                'cogs' => $cogsTotal,
                'profit' => $profit,
                'margin' => $margin,
                'source' => $source,
                'note' => $note,
                'occurred_at' => $occurredAt,
            ]);

            foreach ($product->recipeItems as $item) {
                if (! $item->ingredient) {
                    continue;
                }
                $usage = (float) $item->quantity * $quantity;
                $this->inventory->recordUsage(
                    $item->ingredient,
                    $usage,
                    Sale::class,
                    $sale->id,
                    null,
                    $occurredAt,
                );
            }

            return $sale;
        });
    }

    /**
     * Estimasi pemakaian bahan untuk penjualan (tanpa menyimpan) — dipakai
     * untuk preview / respon bot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function usageBreakdown(Product $product, int $quantity): array
    {
        $product->loadMissing('recipeItems.ingredient');

        $rows = [];
        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient) {
                continue;
            }
            $rows[] = [
                'ingredient' => $item->ingredient->name,
                'quantity' => (float) $item->quantity * $quantity,
                'base_unit' => $item->ingredient->base_unit,
            ];
        }

        return $rows;
    }
}
