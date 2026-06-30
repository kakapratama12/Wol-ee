<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\PriceHistory;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(): Response
    {
        $itemType = request()->query('type', Ingredient::ITEM_RAW_MATERIAL);

        $ingredients = Ingredient::query()
            ->with('supplier:id,name')
            ->where('item_type', $itemType)
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'item_type' => $i->item_type,
                'unit_type' => $i->unit_type,
                'base_unit' => $i->base_unit,
                'unit_price' => (float) $i->unit_price,
                'weighted_avg_price' => (float) $i->weighted_avg_price,
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'supplier' => $i->supplier?->name,
                'status' => $i->stock_status,
            ]);

        $counts = [
            Ingredient::ITEM_RAW_MATERIAL => Ingredient::where('item_type', Ingredient::ITEM_RAW_MATERIAL)->count(),
            Ingredient::ITEM_PREP => Ingredient::where('item_type', Ingredient::ITEM_PREP)->count(),
            Ingredient::ITEM_FINISHED_GOODS => Ingredient::where('item_type', Ingredient::ITEM_FINISHED_GOODS)->count(),
        ];

        return Inertia::render('Inventory/Index', [
            'ingredients' => $ingredients,
            'itemType' => $itemType,
            'counts' => $counts,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'canManage' => $this->isOwner(),
        ]);
    }

    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['current_stock'] = $data['current_stock'] ?? 0;
        $data['weighted_avg_price'] = $data['unit_price'];

        $ingredient = Ingredient::create($data);

        PriceHistory::create([
            'ingredient_id' => $ingredient->id,
            'unit_price' => $ingredient->unit_price,
            'recorded_at' => Carbon::today(),
        ]);

        return back()->with('success', 'Bahan ditambahkan.');
    }

    public function storeJson(StoreIngredientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['current_stock'] = $data['current_stock'] ?? 0;
        $data['weighted_avg_price'] = $data['unit_price'];

        $ingredient = Ingredient::create($data);

        PriceHistory::create([
            'ingredient_id' => $ingredient->id,
            'unit_price' => $ingredient->unit_price,
            'recorded_at' => Carbon::today(),
        ]);

        return response()->json([
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'base_unit' => $ingredient->base_unit,
        ]);
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validated();
        $priceChanged = round((float) $ingredient->unit_price, 4) !== round((float) $data['unit_price'], 4);

        if ($priceChanged) {
            $data['weighted_avg_price'] = $data['unit_price'];
        }

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
            $request->user()->id,
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
