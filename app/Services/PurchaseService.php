<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function void(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $ingredient = $transaction->ingredient ?? Ingredient::findOrFail($transaction->ingredient_id);

            $this->inventory->reversePurchase($transaction);
            $transaction->delete();
            $this->inventory->recalculateWeightedAverage($ingredient->fresh());
        });
    }

    public function update(
        Transaction $transaction,
        Ingredient $ingredient,
        float $quantity,
        float $unitPrice,
        ?string $note = null,
        ?CarbonInterface $occurredAt = null,
        ?int $userId = null,
    ): Transaction {
        return DB::transaction(function () use ($transaction, $ingredient, $quantity, $unitPrice, $note, $occurredAt, $userId) {
            $oldIngredient = $transaction->ingredient ?? Ingredient::findOrFail($transaction->ingredient_id);

            $this->inventory->reversePurchase($transaction);
            $transaction->delete();
            $this->inventory->recalculateWeightedAverage($oldIngredient->fresh());

            if ($oldIngredient->id !== $ingredient->id) {
                $this->inventory->recalculateWeightedAverage($ingredient->fresh());
            }

            return $this->inventory->recordPurchase(
                ingredient: $ingredient,
                quantity: $quantity,
                unitPrice: $unitPrice,
                source: 'web',
                userId: $userId,
                note: $note,
                occurredAt: $occurredAt ?? Carbon::now(),
            );
        });
    }
}
