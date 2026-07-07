<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBatchSaleRequest;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Http\Support\ApiResponse;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // Idempotency check
        if ($idempotencyKey = $request->header('X-Idempotency-Key')) {
            $existing = Sale::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ApiResponse::success('Penjualan sudah tercatat.', [
                    'id' => $existing->id,
                    'product' => $existing->product?->name,
                    'quantity' => $existing->quantity,
                    'revenue' => (float) $existing->revenue,
                    'cogs' => (float) $existing->cogs,
                    'profit' => (float) $existing->profit,
                    'idempotent_replay' => true,
                ]);
            }
        }

        $searchName = trim((string) $request->input('product', ''));
        $product = $this->resolveProduct($request);

        if (! $product) {
            return ApiResponse::error(
                message: "Produk '{$searchName}' tidak ditemukan.",
                errorCode: 'PRODUCT_NOT_FOUND',
                status: 422,
                extra: $this->productNotFoundExtra(),
            );
        }

        $quantity = $request->integer('quantity');
        $unitPrice = $request->filled('total')
            ? round((float) $request->float('total') / max($quantity, 1), 2)
            : ($request->filled('unit_price') ? (float) $request->float('unit_price') : null);

        $sale = $this->sales->record(
            product: $product,
            quantity: $quantity,
            unitPrice: $unitPrice,
            source: 'bot',
            userId: $request->user()?->id,
            note: $request->input('note'),
            occurredAt: $request->date('occurred_at'),
            idempotencyKey: $idempotencyKey,
        );

        return ApiResponse::success('Penjualan tercatat.', [
            'id' => $sale->id,
            'product' => $product->name,
            'quantity' => $sale->quantity,
            'unit_price' => (float) $sale->unit_price,
            'catalog_unit_price' => (float) $product->selling_price,
            'revenue' => (float) $sale->revenue,
            'cogs' => (float) $sale->cogs,
            'profit' => (float) $sale->profit,
            'margin' => (float) $sale->margin,
            'alerts' => $this->lowStockAlerts(),
            'warning' => $sale->unit_price <= 0
                ? 'Harga jual 0 — produk belum di-setup harga atau harga sengaja 0.'
                : null,
        ], 201);
    }

    public function storeBatch(StoreBatchSaleRequest $request): JsonResponse
    {
        $items = $request->input('items', []);
        $resolved = [];
        $errors = [];

        foreach ($items as $index => $item) {
            $searchName = trim((string) ($item['product'] ?? ''));
            $product = $this->resolveProductByInput($item);

            if (! $product) {
                $errors[] = [
                    'index' => $index,
                    'product' => $searchName,
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'message' => "Produk '{$searchName}' tidak ditemukan.",
                ];

                continue;
            }

            $resolved[] = [
                'index' => $index,
                'product' => $product,
                'quantity' => (int) $item['quantity'],
                'unit_price' => ! empty($item['unit_price']) ? (float) $item['unit_price'] : null,
            ];
        }

        if ($errors !== []) {
            return ApiResponse::error(
                message: 'Beberapa produk tidak ditemukan.',
                errorCode: 'BATCH_VALIDATION_FAILED',
                status: 422,
                extra: array_merge(['errors' => $errors], $this->productNotFoundExtra()),
            );
        }

        $note = $request->input('note');
        $occurredAt = $request->date('occurred_at');
        $userId = $request->user()?->id;

        $results = DB::transaction(function () use ($resolved, $note, $occurredAt, $userId) {
            $sales = [];
            $totalRevenue = 0.0;
            $totalProfit = 0.0;

            foreach ($resolved as $row) {
                $sale = $this->sales->record(
                    product: $row['product'],
                    quantity: $row['quantity'],
                    unitPrice: $row['unit_price'],
                    source: 'bot',
                    userId: $userId,
                    note: $note,
                    occurredAt: $occurredAt,
                );

                $totalRevenue += (float) $sale->revenue;
                $totalProfit += (float) $sale->profit;

                $sales[] = [
                    'id' => $sale->id,
                    'product' => $row['product']->name,
                    'quantity' => $sale->quantity,
                    'revenue' => (float) $sale->revenue,
                    'profit' => (float) $sale->profit,
                ];
            }

            return [
                'sales' => $sales,
                'total_revenue' => round($totalRevenue, 2),
                'total_profit' => round($totalProfit, 2),
                'alerts' => $this->lowStockAlerts(),
            ];
        });

        return ApiResponse::success('Batch penjualan tercatat.', $results, 201);
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
     * @param  array<string, mixed>  $item
     */
    private function resolveProductByInput(array $item): ?Product
    {
        if (! empty($item['product_id'])) {
            return Product::find((int) $item['product_id']);
        }

        $name = trim((string) ($item['product'] ?? ''));

        return Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * @return array{available_items: list<string>, dashboard_url: string}
     */
    private function productNotFoundExtra(): array
    {
        return [
            'available_items' => Product::query()
                ->orderBy('name')
                ->limit(50)
                ->pluck('name')
                ->all(),
            'dashboard_url' => rtrim(config('app.url'), '/').'/products',
        ];
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
