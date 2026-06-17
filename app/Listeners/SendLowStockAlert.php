<?php

namespace App\Listeners;

use App\Events\SaleRecorded;
use App\Models\Ingredient;
use App\Support\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Setelah penjualan, periksa bahan yang turun ke level menipis/kritis akibat
 * pemakaian resep, lalu kirim peringatan. Dijalankan di queue agar tidak
 * memperlambat / membuat rapuh pencatatan penjualan.
 */
class SendLowStockAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly TelegramNotifier $notifier) {}

    public function handle(SaleRecorded $event): void
    {
        $sale = $event->sale->loadMissing('product.recipeItems.ingredient');
        $product = $sale->product;

        if (! $product) {
            return;
        }

        $alerting = $product->recipeItems
            ->map(fn ($item) => $item->ingredient)
            ->filter()
            ->unique('id')
            ->filter(fn (Ingredient $ingredient) => in_array(
                $ingredient->stock_status,
                [Ingredient::STATUS_LOW, Ingredient::STATUS_CRITICAL],
                true,
            ));

        if ($alerting->isEmpty()) {
            return;
        }

        $this->notifier->send($this->buildMessage($alerting));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ingredient>  $ingredients
     */
    private function buildMessage($ingredients): string
    {
        $lines = ['<b>Peringatan stok bahan</b>'];

        foreach ($ingredients as $ingredient) {
            $label = $ingredient->stock_status === Ingredient::STATUS_CRITICAL ? 'KRITIS' : 'Menipis';
            $lines[] = sprintf(
                '- %s: %s (sisa %s %s, min %s)',
                $ingredient->name,
                $label,
                rtrim(rtrim((string) $ingredient->current_stock, '0'), '.'),
                $ingredient->base_unit,
                rtrim(rtrim((string) $ingredient->minimum_stock, '0'), '.'),
            );
        }

        return implode("\n", $lines);
    }
}
