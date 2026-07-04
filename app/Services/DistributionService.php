<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Ingredient;
use App\Models\OutletInventory;

class DistributionService
{
    /**
     * Apply inventory changes when creating/updating a distribution.
     * from_outlet_id = null means Gudang Pusat (central warehouse).
     */
    public function applyItems(Distribution $distribution, array $items, int $toOutletId, ?int $fromOutletId): void
    {
        foreach ($items as $item) {
            $isIngredient = $item['item_source'] === 'ingredient';

            $distribution->items()->create([
                'product_id' => $isIngredient ? null : $item['item_id'],
                'ingredient_id' => $isIngredient ? $item['item_id'] : null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
            ]);

            if ($isIngredient) {
                $ingredient = Ingredient::findOrFail($item['item_id']);

                // Deduct from source
                if ($fromOutletId === null) {
                    // Gudang Pusat: deduct from central ingredient stock
                    $ingredient->decrement('current_stock', $item['quantity']);
                } else {
                    // Outlet: deduct from source outlet's ingredient inventory
                    OutletInventory::where([
                        'outlet_id' => $fromOutletId,
                        'ingredient_id' => $item['item_id'],
                        'product_id' => null,
                    ])->decrement('quantity', $item['quantity']);
                }

                // Add to destination outlet ingredient inventory
                $inventory = OutletInventory::firstOrCreate(
                    [
                        'outlet_id' => $toOutletId,
                        'ingredient_id' => $item['item_id'],
                        'product_id' => null,
                    ],
                    [
                        'quantity' => 0,
                        'unit' => $item['unit'],
                        'last_updated' => now(),
                    ]
                );
                $inventory->increment('quantity', $item['quantity']);
                $inventory->update(['unit' => $item['unit'], 'last_updated' => now()]);
            } else {
                // Product distribution
                $inventory = OutletInventory::firstOrCreate(
                    [
                        'outlet_id' => $toOutletId,
                        'product_id' => $item['item_id'],
                        'ingredient_id' => null,
                    ],
                    [
                        'quantity' => 0,
                        'unit' => $item['unit'],
                        'last_updated' => now(),
                    ]
                );
                $inventory->increment('quantity', $item['quantity']);
                $inventory->update(['unit' => $item['unit'], 'last_updated' => now()]);
            }
        }
    }

    /**
     * Reverse inventory changes when deleting/updating a distribution.
     */
    public function reverseItems($items, int $toOutletId, ?int $fromOutletId): void
    {
        foreach ($items as $item) {
            $isIngredient = $item->ingredient_id !== null;

            if ($isIngredient) {
                // Reverse: deduct from destination outlet ingredient inventory
                OutletInventory::where([
                    'outlet_id' => $toOutletId,
                    'ingredient_id' => $item->ingredient_id,
                    'product_id' => null,
                ])->decrement('quantity', $item->quantity);

                // Reverse: add back to source
                if ($fromOutletId === null) {
                    // Gudang Pusat: add back to central ingredient stock
                    $ingredient = Ingredient::findOrFail($item->ingredient_id);
                    $ingredient->increment('current_stock', $item->quantity);
                } else {
                    // Outlet: add back to source outlet's ingredient inventory
                    OutletInventory::where([
                        'outlet_id' => $fromOutletId,
                        'ingredient_id' => $item->ingredient_id,
                        'product_id' => null,
                    ])->increment('quantity', $item->quantity);
                }
            } else {
                // Reverse: deduct from destination outlet product inventory
                OutletInventory::where([
                    'outlet_id' => $toOutletId,
                    'product_id' => $item->product_id,
                    'ingredient_id' => null,
                ])->decrement('quantity', $item->quantity);
            }
        }
    }
}
