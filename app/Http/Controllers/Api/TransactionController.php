<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Models\Ingredient;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $ingredient = $this->resolveIngredient($request);

        if (! $ingredient) {
            return response()->json([
                'message' => 'Bahan tidak ditemukan. Tambahkan dulu lewat dashboard.',
            ], 422);
        }

        $quantity = (float) $request->float('quantity');
        $unitPrice = $request->filled('unit_price')
            ? (float) $request->float('unit_price')
            : round((float) $request->float('total') / max($quantity, 1e-9), 4);

        $transaction = $this->inventory->recordPurchase(
            ingredient: $ingredient,
            quantity: $quantity,
            unitPrice: $unitPrice,
            source: 'bot',
            userId: $request->user()?->id,
            note: $request->input('note'),
            occurredAt: $request->date('occurred_at'),
        );

        $ingredient->refresh();

        return response()->json([
            'message' => 'Pembelian tercatat.',
            'transaction' => [
                'id' => $transaction->id,
                'ingredient' => $ingredient->name,
                'quantity' => (float) $transaction->quantity,
                'base_unit' => $ingredient->base_unit,
                'unit_price' => (float) $transaction->unit_price,
                'total' => (float) $transaction->total,
            ],
            'new_stock' => (float) $ingredient->current_stock,
            'stock_status' => $ingredient->stock_status,
        ], 201);
    }

    private function resolveIngredient(StoreTransactionRequest $request): ?Ingredient
    {
        if ($request->filled('ingredient_id')) {
            return Ingredient::find($request->integer('ingredient_id'));
        }

        $name = trim((string) $request->input('ingredient'));

        return Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }
}
