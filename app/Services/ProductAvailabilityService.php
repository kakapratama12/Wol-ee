<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Tenant;

class ProductAvailabilityService
{
    public const BUCKET_READY = 'ready';
    public const BUCKET_LOW = 'low';
    public const BUCKET_OUT = 'out';

    private const LOW_THRESHOLD = 5;

    public function __construct(
        private readonly BranchStockService $branchStock,
    ) {}

    /**
     * Validate cart items availability.
     *
     * NOTE: We no longer throw CartUnavailableException for stock issues.
     * Stock can go negative — flexible first, audit later.
     * This method now only validates that products exist and are active.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     */
    public function validateCart(array $lineItems, ?int $branchId = null): void
    {
        if ($lineItems === []) {
            throw new \InvalidArgumentException('Keranjang kosong.');
        }

        // Just validate products exist and are active
        $products = Product::query()
            ->whereIn('id', collect($lineItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        foreach ($lineItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new \InvalidArgumentException('Produk tidak ditemukan.');
            }

            if (! $product->is_active) {
                throw new \InvalidArgumentException("Produk {$product->name} tidak aktif.");
            }
        }
    }

    public function estimateMaxPortions(Product $product, ?int $branchId = null): int
    {
        $product->loadMissing('recipeItems.ingredient');

        if ($product->isBatch()) {
            return max(0, (int) floor($this->branchStock->getFinishedGoodsStock($product, $branchId)));
        }

        if ($product->recipeItems->isEmpty()) {
            return 0;
        }

        $max = PHP_INT_MAX;

        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient || (float) $item->quantity <= 0) {
                return 0;
            }

            $stock = $this->branchStock->getIngredientStock($item->ingredient_id, $branchId);
            $portions = (int) floor($stock / (float) $item->quantity);
            $max = min($max, $portions);
        }

        return $max === PHP_INT_MAX ? 0 : max(0, $max);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildOpeningSummary(Tenant $tenant, ?int $branchId = null): array
    {
        $products = Product::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_prep', false)
            ->orderBy('name')
            ->get();

        return $products->map(function (Product $product) use ($branchId) {
            $max = $this->estimateMaxPortions($product, $branchId);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'recipe_type' => $product->recipe_type,
                'max_portions' => $max,
                'bucket' => $this->bucketFor($max),
            ];
        })->values()->all();
    }

    private function bucketFor(int $maxPortions): string
    {
        if ($maxPortions <= 0) {
            return self::BUCKET_OUT;
        }

        if ($maxPortions <= self::LOW_THRESHOLD) {
            return self::BUCKET_LOW;
        }

        return self::BUCKET_READY;
    }
}
