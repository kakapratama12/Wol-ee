<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrepStockController extends Controller
{
    public function index(): Response
    {
        $prepItems = Ingredient::query()
            ->where('item_type', Ingredient::ITEM_PREP)
            ->with(['stockMovements' => function ($q) {
                $q->latest('occurred_at')->limit(20);
            }])
            ->orderBy('name')
            ->get()
            ->map(function (Ingredient $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit_type' => $item->unit_type,
                    'base_unit' => $item->base_unit,
                    'unit_price' => (float) $item->unit_price,
                    'current_stock' => (float) $item->current_stock,
                    'minimum_stock' => (float) $item->minimum_stock,
                    'status' => $item->stock_status,
                    'stock_movements' => $item->stockMovements->map(fn (StockMovement $m) => [
                        'id' => $m->id,
                        'type' => $m->type,
                        'quantity' => (float) $m->quantity,
                        'stock_after' => (float) $m->stock_after,
                        'note' => $m->note,
                        'occurred_at' => $m->occurred_at?->toIso8601String(),
                    ]),
                ];
            });

        return Inertia::render('PrepStock/Index', [
            'prepItems' => $prepItems,
            'canManage' => $this->isOwner(),
        ]);
    }

    public function adjustStock(Request $request, Ingredient $ingredient, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'current_stock' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $inventory->adjustStock(
            $ingredient,
            (float) $validated['current_stock'],
            $validated['note'],
        );

        return back()->with('success', 'Stok prep disesuaikan.');
    }

    private function isOwner(): bool
    {
        return (bool) request()->user()?->isOwner();
    }
}
