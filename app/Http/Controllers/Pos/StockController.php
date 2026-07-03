<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\OutletInventory;
use App\Models\StockMovement;
use App\Services\BranchStockService;
use App\Services\OutletStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private readonly OutletStockService $stockService,
        private readonly BranchStockService $branchStock,
    ) {}

    /**
     * Stock management page for staff.
     * Multi-outlet: shows outlet-specific inventory.
     * Single-outlet: shows ingredients.current_stock directly.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if ($outlet) {
            // Multi-outlet: get outlet inventory with ingredient details
            $outletInventory = OutletInventory::query()
                ->where('outlet_id', $outlet->id)
                ->whereNotNull('ingredient_id')
                ->with('ingredient')
                ->get()
                ->filter(fn ($inv) => $inv->ingredient !== null)
                ->values();

            return Inertia::render('Pos/Stock/Index', [
                'outlet' => [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                ],
                'ingredients' => $outletInventory->map(fn ($inv) => [
                    'id' => $inv->ingredient_id,
                    'name' => $inv->ingredient->name,
                    'unit' => $inv->unit ?: $inv->ingredient->base_unit,
                    'stock' => (float) $inv->quantity,
                ]),
            ]);
        }

        // Single-outlet: show ingredients directly
        $ingredients = Ingredient::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Stock/Index', [
            'outlet' => null,
            'ingredients' => $ingredients->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit,
                'stock' => (float) $i->current_stock,
            ]),
        ]);
    }

    /**
     * Purchase form page.
     * Multi-outlet: shows all raw materials for outlet purchase.
     * Single-outlet: shows all raw materials for direct purchase.
     */
    public function purchaseForm(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        $ingredients = Ingredient::where('item_type', Ingredient::ITEM_RAW_MATERIAL)
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Stock/Purchase', [
            'outlet' => $outlet ? [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ] : null,
            'ingredients' => $ingredients->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit,
            ]),
        ]);
    }

    /**
     * Adjust stock form page.
     * Multi-outlet: shows outlet inventory for adjustment.
     * Single-outlet: shows ingredients.current_stock for adjustment.
     */
    public function adjustForm(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if ($outlet) {
            $outletInventory = OutletInventory::query()
                ->where('outlet_id', $outlet->id)
                ->whereNotNull('ingredient_id')
                ->with('ingredient')
                ->get()
                ->filter(fn ($inv) => $inv->ingredient !== null)
                ->values();

            return Inertia::render('Pos/Stock/Adjust', [
                'outlet' => [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                ],
                'ingredients' => $outletInventory->map(fn ($inv) => [
                    'id' => $inv->ingredient_id,
                    'name' => $inv->ingredient->name,
                    'unit' => $inv->unit ?: $inv->ingredient->base_unit,
                    'stock' => (float) $inv->quantity,
                ]),
                'reasons' => [
                    ['value' => 'rusak', 'label' => 'Rusak'],
                    ['value' => 'expired', 'label' => 'Expired/Kadaluarsa'],
                    ['value' => 'susut', 'label' => 'Susut/Alami'],
                    ['value' => 'lainnya', 'label' => 'Lainnya'],
                ],
            ]);
        }

        $ingredients = Ingredient::query()->orderBy('name')->get();

        return Inertia::render('Pos/Stock/Adjust', [
            'outlet' => null,
            'ingredients' => $ingredients->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit,
                'stock' => (float) $i->current_stock,
            ]),
            'reasons' => [
                ['value' => 'rusak', 'label' => 'Rusak'],
                ['value' => 'expired', 'label' => 'Expired/Kadaluarsa'],
                ['value' => 'susut', 'label' => 'Susut/Alami'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
        ]);
    }

    /**
     * Stock movements history page.
     */
    public function movements(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if (! $outlet) {
            // Single-outlet: movements from stock_movements (global)
            $movements = \App\Models\StockMovement::query()
                ->with('ingredient', 'user')
                ->latest()
                ->limit(50)
                ->get();

            return Inertia::render('Pos/Stock/Movements', [
                'outlet' => null,
                'movements' => $movements->map(fn ($m) => [
                    'id' => $m->id,
                    'ingredient' => $m->ingredient?->name,
                    'type' => $m->type,
                    'quantity' => (float) $m->quantity,
                    'stock_after' => (float) $m->stock_after,
                    'reason' => $m->reason,
                    'note' => $m->note,
                    'user' => $m->user?->name,
                    'occurred_at' => $m->occurred_at?->format('d M Y H:i'),
                ]),
            ]);
        }

        $movements = $this->stockService->getMovements($outlet);

        return Inertia::render('Pos/Stock/Movements', [
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ],
            'movements' => $movements->map(fn ($m) => [
                'id' => $m->id,
                'ingredient' => $m->ingredient?->name,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'stock_after' => (float) $m->stock_after,
                'reason' => $m->reason,
                'note' => $m->note,
                'user' => $m->user?->name,
                'occurred_at' => $m->occurred_at?->format('d M Y H:i'),
            ]),
        ]);
    }

    /**
     * Single-outlet purchase: increase ingredient.current_stock.
     */
    public function purchaseSingle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:255',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);
        $qty = (float) $validated['quantity'];

        $ingredient->increment('current_stock', $qty);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'outlet_id' => null,
            'user_id' => auth()->id(),
            'type' => StockMovement::TYPE_PURCHASE,
            'quantity' => $qty,
            'stock_after' => $ingredient->fresh()->current_stock,
            'note' => $validated['note'] ?? null,
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Pembelian berhasil dicatat.');
    }

    /**
     * Single-outlet adjust: change ingredient.current_stock with reason.
     */
    public function adjustSingle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'adjustment' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'reason' => 'required|in:rusak,expired,susut,lainnya',
            'note' => 'nullable|string|max:255',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);
        $adjustment = (float) $validated['adjustment'];
        $newStock = $ingredient->current_stock + $adjustment;

        $ingredient->update(['current_stock' => $newStock]);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'outlet_id' => null,
            'user_id' => auth()->id(),
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => $adjustment,
            'stock_after' => $newStock,
            'reason' => $validated['reason'],
            'note' => $validated['note'] ?? null,
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }
}
