<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MarginService
{
    /** Ambang penurunan margin (poin persen) untuk memicu alert. */
    private const ALERT_THRESHOLD = 2.0;

    public function __construct(private readonly CogsService $cogs) {}

    /**
     * Margin saat ini + COGS untuk satu produk.
     *
     * @return array<string, mixed>
     */
    public function productMargin(Product $product): array
    {
        $price = (float) $product->selling_price;
        $cogs = $this->cogs->cogsForProduct($product);
        $margin = $price > 0 ? round((($price - $cogs) / $price) * 100, 2) : 0.0;

        return [
            'product_id' => $product->id,
            'product' => $product->name,
            'selling_price' => $price,
            'cogs' => $cogs,
            'margin' => $margin,
        ];
    }

    /**
     * Alert produk yang marginnya turun dibanding ~1 bulan lalu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(?CarbonInterface $now = null): Collection
    {
        $now = $now ?? Carbon::now();
        $reference = $now->copy()->subMonth();

        return Product::query()
            ->where('is_active', true)
            ->with('recipeItems.ingredient.priceHistories')
            ->get()
            ->map(function (Product $product) use ($now, $reference) {
                $price = (float) $product->selling_price;
                if ($price <= 0) {
                    return null;
                }

                $currentCogs = $this->cogsAsOf($product, $now);
                $previousCogs = $this->cogsAsOf($product, $reference);

                $currentMargin = round((($price - $currentCogs) / $price) * 100, 2);
                $previousMargin = round((($price - $previousCogs) / $price) * 100, 2);
                $drop = round($previousMargin - $currentMargin, 2);

                if ($drop < self::ALERT_THRESHOLD) {
                    return null;
                }

                return [
                    'product_id' => $product->id,
                    'product' => $product->name,
                    'selling_price' => $price,
                    'previous_margin' => $previousMargin,
                    'current_margin' => $currentMargin,
                    'margin_drop' => $drop,
                    'previous_cogs' => $previousCogs,
                    'current_cogs' => $currentCogs,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Simulasi dampak kenaikan harga semua bahan sebesar $increasePercent.
     *
     * @return array<string, mixed>
     */
    public function whatIf(Product $product, float $increasePercent): array
    {
        $price = (float) $product->selling_price;
        $currentCogs = $this->cogs->cogsForProduct($product);
        $newCogs = round($currentCogs * (1 + ($increasePercent / 100)), 2);

        $currentMargin = $price > 0 ? round((($price - $currentCogs) / $price) * 100, 2) : 0.0;
        $newMargin = $price > 0 ? round((($price - $newCogs) / $price) * 100, 2) : 0.0;

        // Harga jual agar margin kembali ke level saat ini.
        $recommendedPrice = $currentMargin < 100
            ? round($newCogs / (1 - ($currentMargin / 100)), 2)
            : $price;
        $priceIncreasePercent = $price > 0
            ? round((($recommendedPrice - $price) / $price) * 100, 2)
            : 0.0;

        return [
            'increase_percent' => $increasePercent,
            'current_cogs' => $currentCogs,
            'new_cogs' => $newCogs,
            'current_margin' => $currentMargin,
            'new_margin' => $newMargin,
            'recommended_price' => $recommendedPrice,
            'price_increase_percent' => $priceIncreasePercent,
        ];
    }

    /**
     * Harga jual yang dibutuhkan untuk mencapai target margin (%).
     */
    public function priceForTargetMargin(Product $product, float $targetMargin): float
    {
        if ($targetMargin >= 100) {
            return (float) $product->selling_price;
        }

        $cogs = $this->cogs->cogsForProduct($product);

        return round($cogs / (1 - ($targetMargin / 100)), 2);
    }

    /**
     * Riwayat harga bahan-bahan pada satu produk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ingredientPriceHistory(Product $product): array
    {
        $product->loadMissing('recipeItems.ingredient.priceHistories');

        $rows = [];
        foreach ($product->recipeItems as $item) {
            $ingredient = $item->ingredient;
            if (! $ingredient) {
                continue;
            }

            $history = $ingredient->priceHistories
                ->sortBy('recorded_at')
                ->map(fn ($h) => [
                    'unit_price' => (float) $h->unit_price,
                    'recorded_at' => $h->recorded_at->toDateString(),
                ])
                ->values()
                ->all();

            $rows[] = [
                'ingredient' => $ingredient->name,
                'base_unit' => $ingredient->base_unit,
                'current_price' => (float) $ingredient->unit_price,
                'history' => $history,
            ];
        }

        return $rows;
    }

    private function cogsAsOf(Product $product, CarbonInterface $date): float
    {
        $total = 0.0;
        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient) {
                continue;
            }
            $price = $this->ingredientPriceAsOf($item->ingredient, $date);
            $total += (float) $item->quantity * $price;
        }

        return round($total, 2);
    }

    private function ingredientPriceAsOf(Ingredient $ingredient, CarbonInterface $date): float
    {
        $histories = $ingredient->relationLoaded('priceHistories')
            ? $ingredient->priceHistories
            : $ingredient->priceHistories()->get();

        $atOrBefore = $histories
            ->filter(fn ($h) => $h->recorded_at->lessThanOrEqualTo($date))
            ->sortByDesc('recorded_at')
            ->first();

        if ($atOrBefore) {
            return (float) $atOrBefore->unit_price;
        }

        // Tidak ada harga sebelum tanggal acuan: pakai harga terlama yang ada.
        $earliest = $histories->sortBy('recorded_at')->first();

        return $earliest ? (float) $earliest->unit_price : (float) $ingredient->unit_price;
    }
}
