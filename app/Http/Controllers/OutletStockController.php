<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Services\OutletStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutletStockController extends Controller
{
    public function __construct(
        private readonly OutletStockService $stockService,
    ) {}

    /**
     * Record a direct purchase at outlet.
     */
    public function purchase(Request $request, Outlet $outlet): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:255',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);

        $inventory = $this->stockService->recordPurchase(
            $outlet,
            $ingredient,
            $validated['quantity'],
            $validated['unit'],
            $validated['note'] ?? null,
            Auth::user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pembelian berhasil dicatat.',
                'inventory' => [
                    'quantity' => (float) $inventory->quantity,
                    'unit' => $inventory->unit,
                ],
            ]);
        }

        return back()->with('success', 'Pembelian berhasil dicatat.');
    }

    /**
     * Adjust stock at outlet with reason.
     */
    public function adjust(Request $request, Outlet $outlet): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'adjustment' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'reason' => 'required|in:rusak,expired,susut,lainnya',
            'note' => 'nullable|string|max:255',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);

        $inventory = $this->stockService->adjustStock(
            $outlet,
            $ingredient,
            $validated['adjustment'],
            $validated['unit'],
            $validated['reason'],
            $validated['note'] ?? null,
            Auth::user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Stok berhasil disesuaikan.',
                'inventory' => [
                    'quantity' => (float) $inventory->quantity,
                    'unit' => $inventory->unit,
                ],
            ]);
        }

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }

    /**
     * Get stock movements for an outlet.
     */
    public function movements(Request $request, Outlet $outlet): JsonResponse
    {
        $movements = $this->stockService->getMovements(
            $outlet,
            $request->input('start_date'),
            $request->input('end_date'),
        );

        return response()->json([
            'movements' => $movements->map(fn ($m) => [
                'id' => $m->id,
                'ingredient' => $m->ingredient?->name,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'stock_after' => (float) $m->stock_after,
                'reason' => $m->reason,
                'note' => $m->note,
                'user' => $m->user?->name,
                'occurred_at' => $m->occurred_at?->toIso8601String(),
            ]),
        ]);
    }
}
