<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(): Response
    {
        $sales = Sale::query()
            ->with('product:id,name')
            ->with('outlet:id,name')
            ->latest('occurred_at')
            ->paginate(20)
            ->through(fn (Sale $s) => [
                'id' => $s->id,
                'product_id' => $s->product_id,
                'product' => $s->product?->name,
                'quantity' => $s->quantity,
                'unit_price' => (float) $s->unit_price,
                'revenue' => (float) $s->revenue,
                'cogs' => (float) $s->cogs,
                'source' => $s->source,
                'outlet' => $s->outlet?->name,
                'note' => $s->note,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'products' => Product::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'selling_price']),
        ]);
    }

    public function store(StoreSaleRequest $request, SaleService $sales): RedirectResponse
    {
        try {
            $data = $request->validated();
            $product = Product::findOrFail($data['product_id']);

            $idempotencyKey = $data['idempotency_key'] ?? null;

            $sales->record(
                product: $product,
                quantity: (int) $data['quantity'],
                unitPrice: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                source: 'web',
                userId: $request->user()->id,
                note: $data['note'] ?? null,
                occurredAt: $request->date('occurred_at'),
                idempotencyKey: $idempotencyKey,
            );

            return back()->with('success', 'Penjualan tercatat & stok dikurangi.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(UpdateSaleRequest $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        try {
            $data = $request->validated();
            $product = Product::findOrFail($data['product_id']);

            $sales->update(
                sale: $sale,
                product: $product,
                quantity: (int) $data['quantity'],
                unitPrice: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                note: $data['note'] ?? null,
                occurredAt: $request->date('occurred_at'),
                userId: $request->user()->id,
            );

            return back()->with('success', 'Penjualan diperbarui & stok disesuaikan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Sale $sale, SaleService $sales): RedirectResponse
    {
        try {
            $sales->void($sale);

            return back()->with('success', 'Penjualan dihapus & stok dikembalikan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
