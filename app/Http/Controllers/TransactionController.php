<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Ingredient;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\Transaction;
use App\Services\InventoryService;
use App\Services\PayableService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
            'suppliers' => Partner::where('type', Partner::TYPE_SUPPLIER)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StorePurchaseRequest $request, InventoryService $inventory, PayableService $payableService): RedirectResponse
    {
        $data = $request->validated();
        $ingredient = Ingredient::findOrFail($data['ingredient_id']);

        $quantity = (float) $data['quantity'];
        $unitPrice = round((float) $data['total'] / max($quantity, 1e-9), 4);
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $bayarNanti = $data['bayar_nanti'] ?? false;

        // Create payable first if "Bayar Nanti"
        $payableId = null;
        if ($bayarNanti) {
            $partner = Partner::findOrFail($data['partner_id']);
            if ($partner->type !== Partner::TYPE_SUPPLIER) {
                throw new InvalidArgumentException('Partner harus berupa supplier.');
            }

            $payable = $payableService->create([
                'partner_id' => $data['partner_id'],
                'due_date' => $data['due_date'] ?? null,
                'note' => 'Pembelian bahan: ' . $ingredient->name,
                'items' => [
                    [
                        'description' => $ingredient->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ],
                ],
            ]);
            $payableId = $payable->id;
        }

        // Record purchase & update stock
        $inventory->recordPurchase(
            ingredient: $ingredient,
            quantity: $quantity,
            unitPrice: $unitPrice,
            source: 'web',
            userId: $request->user()->id,
            note: $data['note'] ?? null,
            occurredAt: $request->date('occurred_at'),
            idempotencyKey: $idempotencyKey,
        );

        // Link transaction to payable if exists
        if ($payableId) {
            Transaction::where('ingredient_id', $ingredient->id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->latest()
                ->first()
                ?->update(['payable_id' => $payableId]);
        }

        $message = $bayarNanti
            ? 'Pembelian tercatat & tagihan supplier dibuat.'
            : 'Pembelian tercatat & stok diperbarui.';

        return back()->with('success', $message);
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
            );

            return back()->with('success', 'Pembelian diperbarui.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }
    }

    public function destroy(Transaction $transaction, InventoryService $inventory): RedirectResponse
    {
        $inventory->reversePurchase($transaction);

        $transaction->delete();

        return back()->with('success', 'Pembelian dihapus & stok dikembalikan.');
    }
}
