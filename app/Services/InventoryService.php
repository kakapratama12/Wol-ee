<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\PriceHistory;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

        $ingredient->refresh();
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

    /**
     * Batalkan efek pembelian pada stok (tanpa menghapus row transaksi).
     */
    public function reversePurchase(Transaction $transaction): void
    {
        $ingredient = $transaction->ingredient ?? Ingredient::find($transaction->ingredient_id);

        if (! $ingredient) {
            throw new InvalidArgumentException('Bahan pembelian tidak ditemukan.');
        }

        $ingredient->refresh();
        $quantity = (float) $transaction->quantity;

        if ((float) $ingredient->current_stock < $quantity) {
            throw new InvalidArgumentException(
                'Stok tidak cukup untuk membatalkan pembelian ini. Sebagian bahan sudah terpakai.'
            );
        }

        $ingredient->current_stock = (float) $ingredient->current_stock - $quantity;
        $ingredient->save();

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => StockMovement::TYPE_REVERSAL,
            'quantity' => -1 * $quantity,
            'stock_after' => $ingredient->current_stock,
            'source_type' => Transaction::class,
            'source_id' => $transaction->id,
            'note' => "Reversal pembelian #{$transaction->id}",
            'occurred_at' => Carbon::now(),
        ]);

        StockMovement::query()
            ->where('source_type', Transaction::class)
            ->where('source_id', $transaction->id)
            ->where('type', StockMovement::TYPE_PURCHASE)
            ->delete();
    }

    /**
     * Kembalikan stok bahan yang berkurang akibat penjualan.
     */
    public function reverseSaleUsage(Sale $sale): void
    {
        $movements = StockMovement::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', StockMovement::TYPE_USAGE)
            ->get();

        foreach ($movements as $movement) {
            $ingredient = $movement->ingredient;

            if (! $ingredient) {
                $movement->delete();

                continue;
            }

            $ingredient->refresh();
            $ingredient->current_stock = (float) $ingredient->current_stock + abs((float) $movement->quantity);
            $ingredient->save();
            $movement->delete();
        }
    }

    /**
     * Hitung ulang weighted average dari semua pembelian tersisa.
     */
    public function recalculateWeightedAverage(Ingredient $ingredient): void
    {
        $transactions = Transaction::query()
            ->where('ingredient_id', $ingredient->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $stock = 0.0;
        $weightedAvg = 0.0;
        $lastUnitPrice = (float) $ingredient->unit_price;

        foreach ($transactions as $transaction) {
            $qty = (float) $transaction->quantity;
            $unitPrice = (float) $transaction->unit_price;
            $totalValue = ($stock * $weightedAvg) + ($qty * $unitPrice);
            $stock += $qty;
            $weightedAvg = $stock > 0 ? round($totalValue / $stock, 4) : $unitPrice;
            $lastUnitPrice = $unitPrice;
        }

        $ingredient->weighted_avg_price = $weightedAvg;
        $ingredient->unit_price = $lastUnitPrice;
        $ingredient->save();
    }
}
