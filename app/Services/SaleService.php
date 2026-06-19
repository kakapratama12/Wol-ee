<?php

namespace App\Services;

use App\Events\SaleRecorded;
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
        bool $dispatchSaleRecorded = true,
    ): Sale {
        $sale = DB::transaction(function () use ($product, $quantity, $unitPrice, $source, $userId, $note, $occurredAt) {
            return $this->persistSale(
                product: $product,
                quantity: $quantity,
                unitPrice: $unitPrice,
                source: $source,
                userId: $userId,
                note: $note,
                occurredAt: $occurredAt ?? Carbon::now(),
            );
        });

        if ($dispatchSaleRecorded) {
            SaleRecorded::dispatch($sale);
        }

        return $sale;
    }

    public function void(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $this->inventory->reverseSaleUsage($sale);
            $sale->delete();
        });
    }

    public function update(
        Sale $sale,
        Product $product,
        int $quantity,
        ?float $unitPrice = null,
        ?string $note = null,
        ?CarbonInterface $occurredAt = null,
        ?int $userId = null,
    ): Sale {
        return DB::transaction(function () use ($sale, $product, $quantity, $unitPrice, $note, $occurredAt, $userId) {
            $source = $sale->source;
            $resolvedUserId = $userId ?? $sale->user_id;
            $resolvedOccurredAt = $occurredAt ?? $sale->occurred_at;

            $this->inventory->reverseSaleUsage($sale);
            $sale->delete();

            return $this->persistSale(
                product: $product,
                quantity: $quantity,
                unitPrice: $unitPrice,
                source: $source,
                userId: $resolvedUserId,
                note: $note,
                occurredAt: $resolvedOccurredAt,
            );
        });
    }

    private function persistSale(
        Product $product,
        int $quantity,
        ?float $unitPrice,
        string $source,
        ?int $userId,
        ?string $note,
        CarbonInterface $occurredAt,
    ): Sale {
        $product->loadMissing('recipeItems.ingredient');

        $unitPrice = $unitPrice ?? (float) $product->selling_price;

        // Calculate COGS based on product type
        if ($product->isBatch()) {
            // For batch products: use average COGS from production runs
            $cogsPerUnit = $this->cogs->averageCogsForBatchProduct($product);
        } else {
            // For unit products: use recipe-based COGS
            $cogsPerUnit = $this->cogs->cogsForProduct($product);
        }

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

        // Deduct stock based on product type
        if ($product->isBatch()) {
            // For batch products: deduct from finished goods stock
            $this->inventory->deductFinishedGoods($product, $quantity, $sale, $occurredAt);
        } else {
            // For unit products: deduct from raw materials based on recipe
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
        }

        return $sale;
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
