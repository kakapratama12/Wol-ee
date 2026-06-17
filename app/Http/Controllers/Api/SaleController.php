<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Http\Support\ApiResponse;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 10), 50);

        $query = Sale::query()
            ->with('product:id,name')
            ->latest('occurred_at');

        if ($request->filled('date')) {
            $query->whereDate('occurred_at', $request->date('date'));
        }

        $sales = $query
            ->limit($limit)
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'product' => $sale->product?->name,
                'quantity' => $sale->quantity,
                'unit_price' => (float) $sale->unit_price,
                'revenue' => (float) $sale->revenue,
                'cogs' => (float) $sale->cogs,
                'profit' => (float) $sale->profit,
                'margin' => (float) $sale->margin,
                'source' => $sale->source,
                'note' => $sale->note,
                'occurred_at' => $sale->occurred_at?->toIso8601String(),
            ]);

        return ApiResponse::success('Riwayat penjualan.', $sales->values()->all());
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $searchName = trim((string) $request->input('product', ''));
        $product = $this->resolveProduct($request);

        if (! $product) {
            $extra = [];

            if ($searchName !== '') {
                $extra['suggestions'] = $this->suggestProducts($searchName);
            }

            return ApiResponse::error(
                message: "Produk '{$searchName}' tidak ditemukan.",
                errorCode: 'PRODUCT_NOT_FOUND',
                status: 422,
                extra: $extra,
            );
        }

        $quantity = $request->integer('quantity');

        $sale = $this->sales->record(
            product: $product,
            quantity: $quantity,
            unitPrice: $request->filled('unit_price') ? (float) $request->float('unit_price') : null,
            source: 'bot',
            userId: $request->user()?->id,
            note: $request->input('note'),
            occurredAt: $request->date('occurred_at'),
        );

        return ApiResponse::success('Penjualan tercatat.', [
            'id' => $sale->id,
            'product' => $product->name,
            'quantity' => $sale->quantity,
            'revenue' => (float) $sale->revenue,
            'cogs' => (float) $sale->cogs,
            'profit' => (float) $sale->profit,
            'margin' => (float) $sale->margin,
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
     * @return list<string>
     */
    private function suggestProducts(string $search): array
    {
        return Product::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
            ->orderBy('name')
            ->limit(3)
            ->pluck('name')
            ->all();
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
                'current' => (float) $i->current_stock,
                'minimum' => (float) $i->minimum_stock,
                'base_unit' => $i->base_unit,
                'status' => $i->stock_status,
            ])
            ->all();
    }
}
