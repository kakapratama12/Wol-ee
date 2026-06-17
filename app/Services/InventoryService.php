<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\PriceHistory;
use App\Models\StockMovement;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Catat pembelian bahan baku: tambah stok, update harga terakhir,
     * simpan riwayat harga, dan catat transaksi + stock movement. Atomic.
     */
    public function recordPurchase(
        Ingredient $ingredient,
        float $quantity,
        float $unitPrice,
        string $source = 'web',
        ?int $userId = null,
        ?string $note = null,
        ?CarbonInterface $occurredAt = null,
    ): Transaction {
        return DB::transaction(function () use ($ingredient, $quantity, $unitPrice, $source, $userId, $note, $occurredAt) {
            $occurredAt = $occurredAt ?? Carbon::now();
            $total = round($quantity * $unitPrice, 2);

            $transaction = Transaction::create([
                'user_id' => $userId,
                'ingredient_id' => $ingredient->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
                'source' => $source,
                'note' => $note,
                'occurred_at' => $occurredAt,
            ]);

            $priceChanged = round((float) $ingredient->unit_price, 4) !== round($unitPrice, 4);

            $oldStock = (float) $ingredient->current_stock;
            $oldWeightedAvg = (float) ($ingredient->weighted_avg_price ?: $ingredient->unit_price);
            $newStock = $oldStock + $quantity;
            $totalValue = ($oldStock * $oldWeightedAvg) + ($quantity * $unitPrice);

            $ingredient->current_stock = $newStock;
            $ingredient->unit_price = $unitPrice;
            $ingredient->weighted_avg_price = $newStock > 0
                ? round($totalValue / $newStock, 4)
                : $unitPrice;
            $ingredient->save();

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => StockMovement::TYPE_PURCHASE,
                'quantity' => $quantity,
                'stock_after' => $ingredient->current_stock,
                'source_type' => Transaction::class,
                'source_id' => $transaction->id,
                'note' => $note,
                'occurred_at' => $occurredAt,
            ]);

            if ($priceChanged || ! $ingredient->priceHistories()->exists()) {
                PriceHistory::create([
                    'ingredient_id' => $ingredient->id,
                    'unit_price' => $unitPrice,
                    'recorded_at' => $occurredAt->toDateString(),
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Kurangi stok (pemakaian). Tidak membuka transaksi sendiri agar bisa
     * dipanggil dari dalam DB::transaction lain (mis. saat penjualan).
     */
    public function recordUsage(
        Ingredient $ingredient,
        float $quantity,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        $occurredAt = $occurredAt ?? Carbon::now();

        $ingredient->current_stock = (float) $ingredient->current_stock - $quantity;
        $ingredient->save();

        return StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => StockMovement::TYPE_USAGE,
            'quantity' => -1 * $quantity,
            'stock_after' => $ingredient->current_stock,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'note' => $note,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * Setel stok ke nilai absolut (koreksi manual). Atomic.
     */
    public function adjustStock(Ingredient $ingredient, float $newStock, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($ingredient, $newStock, $note) {
            $delta = $newStock - (float) $ingredient->current_stock;
            $ingredient->current_stock = $newStock;
            $ingredient->save();

            return StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $delta,
                'stock_after' => $newStock,
                'note' => $note,
                'occurred_at' => Carbon::now(),
            ]);
        });
    }
}
