<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $product = $this->resolveProduct($request);

        if (! $product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan. Tambahkan dulu lewat dashboard.',
            ], 422);
        }

        $quantity = $request->integer('quantity');
        $usage = $this->sales->usageBreakdown($product, $quantity);

        $sale = $this->sales->record(
            product: $product,
            quantity: $quantity,
            unitPrice: $request->filled('unit_price') ? (float) $request->float('unit_price') : null,
            source: 'bot',
            userId: $request->user()?->id,
            note: $request->input('note'),
            occurredAt: $request->date('occurred_at'),
        );

        return response()->json([
            'message' => 'Penjualan tercatat.',
            'sale' => [
                'id' => $sale->id,
                'product' => $product->name,
                'quantity' => $sale->quantity,
                'revenue' => (float) $sale->revenue,
                'cogs' => (float) $sale->cogs,
                'profit' => (float) $sale->profit,
                'margin' => (float) $sale->margin,
            ],
            'usage' => $usage,
            'alerts' => $this->lowStockAlerts(),
        ], 201);
    }

    private function resolveProduct(StoreSaleRequest $request): ?Product
    {
        if ($request->filled('product_id')) {
            return Product::find($request->integer('product_id'));
        }

        $name = trim((string) $request->input('product'));

        return Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStockAlerts(): array
    {
        return Ingredient::query()
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->get()
            ->map(fn (Ingredient $i) => [
                'ingredient' => $i->name,
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'base_unit' => $i->base_unit,
                'status' => $i->stock_status,
            ])
            ->all();
    }
}
