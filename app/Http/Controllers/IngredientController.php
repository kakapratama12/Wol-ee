<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\PriceHistory;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(): Response
    {
        $ingredients = Ingredient::query()
            ->with('supplier:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit_type' => $i->unit_type,
                'base_unit' => $i->base_unit,
                'unit_price' => (float) $i->unit_price,
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'supplier' => $i->supplier?->name,
                'status' => $i->stock_status,
            ]);

        return Inertia::render('Inventory/Index', [
            'ingredients' => $ingredients,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'canManage' => $this->isOwner(),
        ]);
    }

    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['current_stock'] = $data['current_stock'] ?? 0;

        $ingredient = Ingredient::create($data);

        PriceHistory::create([
            'ingredient_id' => $ingredient->id,
            'unit_price' => $ingredient->unit_price,
            'recorded_at' => Carbon::today(),
        ]);

        return back()->with('success', 'Bahan ditambahkan.');
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validated();
        $priceChanged = round((float) $ingredient->unit_price, 4) !== round((float) $data['unit_price'], 4);

        $ingredient->update($data);

        if ($priceChanged) {
            PriceHistory::create([
                'ingredient_id' => $ingredient->id,
                'unit_price' => $data['unit_price'],
                'recorded_at' => Carbon::today(),
            ]);
        }

        return back()->with('success', 'Bahan diperbarui.');
    }

    public function adjust(AdjustStockRequest $request, Ingredient $ingredient, InventoryService $inventory): RedirectResponse
    {
        $inventory->adjustStock(
            $ingredient,
            (float) $request->validated()['current_stock'],
            $request->input('note'),
        );

        return back()->with('success', 'Stok disesuaikan.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return back()->with('success', 'Bahan dihapus.');
    }

    private function isOwner(): bool
    {
        return (bool) request()->user()?->isOwner();
    }
}
