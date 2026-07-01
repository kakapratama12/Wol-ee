<?php

namespace App\Services;

use App\Exceptions\CartUnavailableException;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Tenant;

class ProductAvailabilityService
{
    public const BUCKET_READY = 'ready';
    public const BUCKET_LOW = 'low';
    public const BUCKET_OUT = 'out';

    private const LOW_THRESHOLD = 5;

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     *
     * @throws CartUnavailableException
     */
    public function validateCart(array $lineItems): void
    {
        if ($lineItems === []) {
            throw new CartUnavailableException('Keranjang kosong.');
        }

        if ($this->canFulfillCart($lineItems)) {
            return;
        }

        $unavailable = [];

        foreach ($lineItems as $item) {
            $product = Product::query()->find($item['product_id']);

            if (! $product) {
                continue;
            }

            $requested = (int) $item['quantity'];
            $max = $this->maxFulfillableQuantityInCart($lineItems, (int) $product->id);

            if ($max < $requested) {
                $unavailable[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'requested_qty' => $requested,
                    'max_fulfillable_qty' => $max,
                ];
            }
        }

        $message = count($unavailable) === 1
            ? sprintf(
                '%s tidak tersedia (diminta %d). Stok cukup untuk maks. %d porsi.',
                $unavailable[0]['name'],
                $unavailable[0]['requested_qty'],
                $unavailable[0]['max_fulfillable_qty'],
            )
            : sprintf(
                '%d produk tidak bisa diproses. Periksa item yang ditandai di keranjang.',
                count($unavailable),
            );

        throw new CartUnavailableException($message, $unavailable);
    }

    public function estimateMaxPortions(Product $product): int
    {
        $product->loadMissing('recipeItems.ingredient');

        if ($product->isBatch()) {
            return max(0, (int) floor($this->finishedGoodsStock($product)));
        }

        if ($product->recipeItems->isEmpty()) {
            return 0;
        }

        $max = PHP_INT_MAX;

        foreach ($product->recipeItems as $item) {
            if (! $item->ingredient || (float) $item->quantity <= 0) {
                return 0;
            }

            $portions = (int) floor((float) $item->ingredient->current_stock / (float) $item->quantity);
            $max = min($max, $portions);
        }

        return $max === PHP_INT_MAX ? 0 : max(0, $max);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildOpeningSummary(Tenant $tenant): array
    {
        $products = Product::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_prep', false)
            ->orderBy('name')
            ->get();

        return $products->map(function (Product $product) {
            $max = $this->estimateMaxPortions($product);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'recipe_type' => $product->recipe_type,
                'max_portions' => $max,
                'bucket' => $this->bucketFor($max),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     */
    private function canFulfillCart(array $lineItems): bool
    {
        $products = $this->loadProductsForCart($lineItems);

        foreach ($lineItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                return false;
            }

            if ($product->isBatch()) {
                if ($this->finishedGoodsStock($product) < (int) $item['quantity']) {
                    return false;
                }
            }
        }

        $demand = $this->aggregateIngredientDemand($lineItems, $products);

        foreach ($demand as $ingredientId => $required) {
            $ingredient = Ingredient::query()->find($ingredientId);

            if (! $ingredient || (float) $ingredient->current_stock < $required) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     */
    private function maxFulfillableQuantityInCart(array $lineItems, int $productId): int
    {
        $requested = (int) collect($lineItems)->firstWhere('product_id', $productId)['quantity'];

        for ($qty = $requested; $qty >= 0; $qty--) {
            $testCart = collect($lineItems)
                ->map(fn (array $row) => $row['product_id'] === $productId
                    ? ['product_id' => $row['product_id'], 'quantity' => $qty]
                    : $row)
                ->values()
                ->all();

            if ($this->canFulfillCart($testCart)) {
                return $qty;
            }
        }

        return 0;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     * @return array<int, float>
     */
    private function aggregateIngredientDemand(array $lineItems, \Illuminate\Support\Collection $products): array
    {
        $demand = [];

        foreach ($lineItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product || $product->isBatch()) {
                continue;
            }

            $product->loadMissing('recipeItems.ingredient');

            foreach ($product->recipeItems as $recipeItem) {
                if (! $recipeItem->ingredient) {
                    continue;
                }

                $ingredientId = $recipeItem->ingredient_id;
                $demand[$ingredientId] = ($demand[$ingredientId] ?? 0) + ((float) $recipeItem->quantity * (int) $item['quantity']);
            }
        }

        return $demand;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function loadProductsForCart(array $lineItems): \Illuminate\Support\Collection
    {
        $ids = collect($lineItems)->pluck('product_id')->unique()->values();

        return Product::query()
            ->whereIn('id', $ids)
            ->with('recipeItems.ingredient')
            ->get()
            ->keyBy('id');
    }

    private function finishedGoodsStock(Product $product): float
    {
        $finishedGoodsName = "{$product->name} ( Produk Jadi )";
        $finishedGoods = Ingredient::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('name', $finishedGoodsName)
            ->first();

        return $finishedGoods ? (float) $finishedGoods->current_stock : 0.0;
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
