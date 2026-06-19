<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Ingredient;
use App\Models\Transaction;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function index(): Response
    {
        $transactions = Transaction::query()
            ->with('ingredient:id,name,base_unit')
            ->latest('occurred_at')
            ->paginate(20)
            ->through(fn (Transaction $t) => [
                'id' => $t->id,
                'ingredient_id' => $t->ingredient_id,
                'ingredient' => $t->ingredient?->name,
                'base_unit' => $t->ingredient?->base_unit,
                'quantity' => (float) $t->quantity,
                'unit_price' => (float) $t->unit_price,
                'total' => (float) $t->total,
                'source' => $t->source,
                'note' => $t->note,
                'occurred_at' => $t->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'base_unit']),
        ]);
    }

    public function store(StorePurchaseRequest $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validated();
        $ingredient = Ingredient::findOrFail($data['ingredient_id']);

        $quantity = (float) $data['quantity'];
        $unitPrice = round((float) $data['total'] / max($quantity, 1e-9), 4);

        $inventory->recordPurchase(
            ingredient: $ingredient,
            quantity: $quantity,
            unitPrice: $unitPrice,
            source: 'web',
            userId: $request->user()->id,
            note: $data['note'] ?? null,
            occurredAt: $request->date('occurred_at'),
        );

        return back()->with('success', 'Pembelian tercatat & stok diperbarui.');
    }

    public function update(UpdatePurchaseRequest $request, Transaction $transaction, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validated();
        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $quantity = (float) $data['quantity'];
        $unitPrice = round((float) $data['total'] / max($quantity, 1e-9), 4);

        try {
            $purchases->update(
                transaction: $transaction,
                ingredient: $ingredient,
                quantity: $quantity,
                unitPrice: $unitPrice,
                note: $data['note'] ?? null,
                occurredAt: $request->date('occurred_at'),
                userId: $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembelian diperbarui & stok disesuaikan.');
    }

    public function destroy(Transaction $transaction, PurchaseService $purchases): RedirectResponse
    {
        try {
            $purchases->void($transaction);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembelian dihapus & stok dikurangi.');
    }
}
