<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OutletStockService
{
    /**
     * Record a direct purchase at outlet (adds stock + records movement).
     */
    public function recordPurchase(
        Outlet $outlet,
        Ingredient $ingredient,
        float $quantity,
        string $unit,
        ?string $note,
        User $user,
    ): OutletInventory {
        return DB::transaction(function () use ($outlet, $ingredient, $quantity, $unit, $note, $user) {
            // Find or create inventory record
            $inventory = OutletInventory::firstOrCreate(
                [
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $ingredient->id,
                    'product_id' => null,
                ],
                [
                    'quantity' => 0,
                    'unit' => $unit,
                ]
            );

            // Add stock
            $inventory->addQuantity($quantity);

            // Record movement
            StockMovement::create([
                'tenant_id' => $outlet->tenant_id,
                'ingredient_id' => $ingredient->id,
                'outlet_id' => $outlet->id,
                'user_id' => $user->id,
                'type' => StockMovement::TYPE_PURCHASE,
                'quantity' => $quantity,
                'stock_after' => $inventory->quantity,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            return $inventory;
        });
    }

    /**
     * Adjust stock at outlet with reason (logged).
     */
    public function adjustStock(
        Outlet $outlet,
        Ingredient $ingredient,
        float $adjustment,
        string $unit,
        ?string $reason,
        ?string $note,
        User $user,
    ): OutletInventory {
        return DB::transaction(function () use ($outlet, $ingredient, $adjustment, $unit, $reason, $note, $user) {
            // Find or create inventory record
            $inventory = OutletInventory::firstOrCreate(
                [
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $ingredient->id,
                    'product_id' => null,
                ],
                [
                    'quantity' => 0,
                    'unit' => $unit,
                ]
            );

            // Adjust stock
            if ($adjustment > 0) {
                $inventory->addQuantity($adjustment);
            } else {
                $inventory->subtractQuantity(abs($adjustment));
            }

            // Record movement
            StockMovement::create([
                'tenant_id' => $outlet->tenant_id,
                'ingredient_id' => $ingredient->id,
                'outlet_id' => $outlet->id,
                'user_id' => $user->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $adjustment,
                'stock_after' => $inventory->quantity,
                'reason' => $reason,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            return $inventory;
        });
    }

    /**
     * Get stock movements for an outlet (with optional date range).
     */
    public function getMovements(
        Outlet $outlet,
        ?string $startDate = null,
        ?string $endDate = null,
    ) {
        $query = StockMovement::query()
            ->where('outlet_id', $outlet->id)
            ->with('ingredient');

        if ($startDate) {
            $query->where('occurred_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('occurred_at', '<=', $endDate);
        }

        return $query->orderByDesc('occurred_at')->get();
    }
}
