<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\OutletInventory;
use App\Models\StockMovement;
use App\Services\OutletStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private readonly OutletStockService $stockService,
    ) {}

    /**
     * Stock management page — always reads from outlet_inventories.
     */
    public function index(): Response
    {
        $outlet = $this->getOutlet();

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

    /**
     * Purchase form page — shows all raw materials for outlet purchase.
     */
    public function purchaseForm(): Response
    {
        $outlet = $this->getOutlet();

        $ingredients = Ingredient::where('item_type', Ingredient::ITEM_RAW_MATERIAL)
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Stock/Purchase', [
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ],
            'ingredients' => $ingredients->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit,
            ]),
        ]);
    }

    /**
     * Adjust stock form page — shows outlet inventory for adjustment.
     */
    public function adjustForm(): Response
    {
        $outlet = $this->getOutlet();

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

    /**
     * Stock movements history page.
     */
    public function movements(): Response
    {
        $outlet = $this->getOutlet();

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
     * Purchase — increase outlet inventory.
     */
    public function purchase(Request $request): RedirectResponse
    {
        $outlet = $this->getOutlet();

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
            auth()->user(),
        );

        return back()->with('success', 'Pembelian berhasil dicatat.');
    }

    /**
     * Adjust stock with reason.
     */
    public function adjust(Request $request): RedirectResponse
    {
        $outlet = $this->getOutlet();

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
            auth()->user(),
        );

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }

    /**
     * Get the authenticated user's outlet.
     */
    private function getOutlet()
    {
        $outlet = auth()->user()->outlet;

        abort_unless($outlet, 403, 'Anda belum di-assign ke outlet.');

        return $outlet;
    }
}
