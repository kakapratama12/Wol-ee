<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
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
     * Stock management page for staff.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if (! $outlet) {
            return redirect()->route('pos.landing')->with('error', 'Anda belum di-assign ke outlet.');
        }

        $ingredients = Ingredient::where('item_type', Ingredient::ITEM_RAW_MATERIAL)
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Stock/Index', [
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
     * Purchase form page.
     */
    public function purchaseForm(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if (! $outlet) {
            return redirect()->route('pos.landing')->with('error', 'Anda belum di-assign ke outlet.');
        }

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
     * Adjust stock form page.
     */
    public function adjustForm(): Response
    {
        $user = auth()->user();
        $outlet = $user->outlet;

        if (! $outlet) {
            return redirect()->route('pos.landing')->with('error', 'Anda belum di-assign ke outlet.');
        }

        $ingredients = Ingredient::where('item_type', Ingredient::ITEM_RAW_MATERIAL)
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Stock/Adjust', [
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ],
            'ingredients' => $ingredients->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit,
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
            return redirect()->route('pos.landing')->with('error', 'Anda belum di-assign ke outlet.');
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
}
