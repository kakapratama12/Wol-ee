<?php

namespace App\Services;

use App\Events\SaleRecorded;
use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        ?string $idempotencyKey = null,
        ?int $posOrderId = null,
        ?int $outletId = null,
    ): Sale {
        if ($idempotencyKey !== null) {
            $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }
        }

        $sale = DB::transaction(function () use ($product, $quantity, $unitPrice, $source, $userId, $note, $occurredAt, $idempotencyKey, $posOrderId, $outletId) {
            return $this->persistSale(
                product: $product,
                quantity: $quantity,
                unitPrice: $unitPrice,
                source: $source,
                userId: $userId,
                note: $note,
                occurredAt: $occurredAt ?? Carbon::now(),
                idempotencyKey: $idempotencyKey,
                posOrderId: $posOrderId,
                outletId: $outletId,
            );
        });

        if ($dispatchSaleRecorded) {
            SaleRecorded::dispatch($sale);
        }

        return $sale;
    }

    public function void(Sale $sale): void
    {
        if ($sale->isVoid()) {
            throw new InvalidArgumentException('Penjualan sudah di-void.');
        }

        DB::transaction(function () use ($sale) {
            $this->inventory->reverseSaleUsage($sale);
            $sale->update(['status' => Sale::STATUS_VOID]);
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
        if ($sale->isVoid()) {
            throw new InvalidArgumentException('Penjualan void tidak bisa diubah.');
        }

        return DB::transaction(function () use ($sale, $product, $quantity, $unitPrice, $note, $occurredAt, $userId) {
            $source = $sale->source;
            $resolvedUserId = $userId ?? $sale->user_id;
            $resolvedOccurredAt = $occurredAt ?? $sale->occurred_at;
            $posOrderId = $sale->pos_order_id;
            $outletId = $sale->outlet_id;

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
                posOrderId: $posOrderId,
                outletId: $outletId,
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
        ?string $idempotencyKey = null,
        ?int $posOrderId = null,
        ?int $outletId = null,
    ): Sale {
        $product->loadMissing('recipeItems.ingredient');

        $unitPrice = $unitPrice ?? (float) $product->selling_price;

        if ($product->isBatch()) {
            $cogsPerUnit = $this->cogs->averageCogsForBatchProduct($product, $occurredAt);
        } else {
            $cogsPerUnit = $this->cogs->cogsForProduct($product);
        }

        $revenue = round($unitPrice * $quantity, 2);
        $cogsTotal = round($cogsPerUnit * $quantity, 2);
        $profit = round($revenue - $cogsTotal, 2);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

        $sale = Sale::create([
            'idempotency_key' => $idempotencyKey,
            'user_id' => $userId,
            'product_id' => $product->id,
            'pos_order_id' => $posOrderId,
            'outlet_id' => $outletId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'revenue' => $revenue,
            'cogs' => $cogsTotal,
            'profit' => $profit,
            'margin' => $margin,
            'source' => $source,
            'status' => Sale::STATUS_ACTIVE,
            'note' => $note,
            'occurred_at' => $occurredAt,
        ]);

        if ($product->isBatch()) {
            $this->inventory->deductFinishedGoods($product, $quantity, $sale, $occurredAt, $userId);
        } else {
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
                    $userId,
                );
            }
        }

        return $sale;
    }

    /**
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
