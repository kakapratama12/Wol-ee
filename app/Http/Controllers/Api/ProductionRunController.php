<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductionRunRequest;
use App\Http\Support\ApiResponse;
use App\Models\Product;
use App\Models\ProductionRun;
use App\Services\ProductionRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionRunController extends Controller
{
    public function __construct(
        private readonly ProductionRunService $productionRunService,
    ) {}

    /**
     * List production runs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 10), 50);

        $query = ProductionRun::query()
            ->with('product:id,name,unit')
            ->latest('produced_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('produced_at', $request->date('date'));
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('produced_at', [
                $request->date('from'), $request->date('to'),
            ]);
        }

        $runs = $query
            ->limit($limit)
            ->get()
            ->map(fn (ProductionRun $run) => [
                'id' => $run->id,
                'product' => $run->product?->name,
                'batch_count' => $run->batch_count,
                'yield_actual' => $run->yield_actual,
                'waste_count' => $run->waste_count,
                'total_cost' => (float) $run->total_cost,
                'cost_per_unit' => $run->getCostPerUnit(),
                'yield_per_batch' => $run->getYieldPerBatch(),
                'waste_percentage' => $run->getWastePercentage(),
                'notes' => $run->notes,
                'produced_at' => $run->produced_at?->toIso8601String(),
            ]);

        return ApiResponse::success('Riwayat produksi.', $runs->values()->all());
    }

    /**
     * Create a production run (auto-uses recipe quantities).
     */
    public function store(StoreProductionRunRequest $request): JsonResponse
    {
        $product = Product::find($request->integer('product_id'));

        if (! $product) {
            return ApiResponse::error(
                message: 'Produk tidak ditemukan.',
                errorCode: 'PRODUCT_NOT_FOUND',
                status: 422,
            );
        }

        if (! $product->isBatch()) {
            return ApiResponse::error(
                message: "Produk \"{$product->name}\" bukan tipe batch. Gunakan input penjualan biasa.",
                errorCode: 'NOT_BATCH_PRODUCT',
                status: 422,
            );
        }

        try {
            $productionRun = $this->productionRunService->create(
                product: $product,
                batchCount: $request->integer('batch_count'),
                notes: $request->input('notes'),
                producedAt: $request->date('produced_at'),
            );

            $productionRun->load('items.ingredient');

            return ApiResponse::success('Produksi tercatat.', [
                'id' => $productionRun->id,
                'product' => $product->name,
                'batch_count' => $productionRun->batch_count,
                'yield_actual' => $productionRun->yield_actual,
                'waste_count' => $productionRun->waste_count,
                'total_cost' => (float) $productionRun->total_cost,
                'cost_per_unit' => $productionRun->getCostPerUnit(),
                'items' => $productionRun->items->map(fn ($item) => [
                    'ingredient' => $item->ingredient?->name,
                    'quantity_used' => (float) $item->quantity_used,
                    'unit_cost_snapshot' => (float) $item->unit_cost_snapshot,
                    'total_cost' => $item->getTotalCost(),
                ]),
                'produced_at' => $productionRun->produced_at?->toIso8601String(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }
    }

    /**
     * Show a single production run detail.
     */
    public function show(ProductionRun $productionRun): JsonResponse
    {
        $productionRun->load('items.ingredient', 'product');

        return ApiResponse::success('Detail produksi.', [
            'id' => $productionRun->id,
            'product' => $productionRun->product?->name,
            'batch_count' => $productionRun->batch_count,
            'yield_actual' => $productionRun->yield_actual,
            'waste_count' => $productionRun->waste_count,
            'total_cost' => (float) $productionRun->total_cost,
            'cost_per_unit' => $productionRun->getCostPerUnit(),
            'yield_per_batch' => $productionRun->getYieldPerBatch(),
            'waste_percentage' => $productionRun->getWastePercentage(),
            'items' => $productionRun->items->map(fn ($item) => [
                'ingredient' => $item->ingredient?->name,
                'quantity_used' => (float) $item->quantity_used,
                'unit_cost_snapshot' => (float) $item->unit_cost_snapshot,
                'total_cost' => $item->getTotalCost(),
            ]),
            'notes' => $productionRun->notes,
            'produced_at' => $productionRun->produced_at?->toIso8601String(),
        ]);
    }

    /**
     * Reverse (delete) a production run.
     */
    public function destroy(ProductionRun $productionRun): JsonResponse
    {
        try {
            $this->productionRunService->reverse($productionRun);

            return ApiResponse::success('Produksi dibatalkan. Stok dikembalikan.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                errorCode: 'REVERSAL_ERROR',
                status: 422,
            );
        }
    }
}
