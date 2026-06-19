     1|<?php
     2|
     3|namespace App\Http\Controllers;
     4|
     5|use App\Http\Requests\AdjustStockRequest;
     6|use App\Http\Requests\StoreIngredientRequest;
     7|use App\Http\Requests\UpdateIngredientRequest;
     8|use App\Models\Ingredient;
     9|use App\Models\PriceHistory;
    10|use App\Models\Supplier;
    11|use App\Services\InventoryService;
    12|use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
    13|use Illuminate\Support\Carbon;
    14|use Inertia\Inertia;
    15|use Inertia\Response;
    16|
    17|class IngredientController extends Controller
    18|{
    19|    public function index(): Response
    20|    {
    21|        $ingredients = Ingredient::query()
    22|            ->with('supplier:id,name')
    23|            ->orderBy('name')
    24|            ->get()
    25|            ->map(fn (Ingredient $i) => [
    26|                'id' => $i->id,
    27|                'name' => $i->name,
    28|                'unit_type' => $i->unit_type,
    29|                'base_unit' => $i->base_unit,
    30|                'unit_price' => (float) $i->unit_price,
    31|                'weighted_avg_price' => (float) $i->weighted_avg_price,
    32|                'current_stock' => (float) $i->current_stock,
    33|                'minimum_stock' => (float) $i->minimum_stock,
    34|                'supplier' => $i->supplier?->name,
    35|                'status' => $i->stock_status,
    36|            ]);
    37|
    38|        return Inertia::render('Inventory/Index', [
    39|            'ingredients' => $ingredients,
    40|            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
    41|            'canManage' => $this->isOwner(),
    42|        ]);
    43|    }
    44|
    45|    public function store(StoreIngredientRequest $request): RedirectResponse
    46|    {
    47|        $data = $request->validated();
    48|        $data['current_stock'] = $data['current_stock'] ?? 0;
    49|        $data['weighted_avg_price'] = $data['unit_price'];
    50|
    51|        $ingredient = Ingredient::create($data);
    52|
    53|        PriceHistory::create([
    54|            'ingredient_id' => $ingredient->id,
    55|            'unit_price' => $ingredient->unit_price,
    56|            'recorded_at' => Carbon::today(),
    57|        ]);
    58|
    59|        return back()->with('success', 'Bahan ditambahkan.');
    60|    }
    61|
    62|    public function storeJson(StoreIngredientRequest $request): JsonResponse
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
    63|    {
    64|        $data = $request->validated();
    65|        $priceChanged = round((float) $ingredient->unit_price, 4) !== round((float) $data['unit_price'], 4);
    66|
    67|        if ($priceChanged) {
    68|            $data['weighted_avg_price'] = $data['unit_price'];
    69|        }
    70|
    71|        $ingredient->update($data);
    72|
    73|        if ($priceChanged) {
    74|            PriceHistory::create([
    75|                'ingredient_id' => $ingredient->id,
    76|                'unit_price' => $data['unit_price'],
    77|                'recorded_at' => Carbon::today(),
    78|            ]);
    79|        }
    80|
    81|        return back()->with('success', 'Bahan diperbarui.');
    82|    }
    83|
    84|    public function adjust(AdjustStockRequest $request, Ingredient $ingredient, InventoryService $inventory): RedirectResponse
    85|    {
    86|        $inventory->adjustStock(
    87|            $ingredient,
    88|            (float) $request->validated()['current_stock'],
    89|            $request->input('note'),
    90|        );
    91|
    92|        return back()->with('success', 'Stok disesuaikan.');
    93|    }
    94|
    95|    public function destroy(Ingredient $ingredient): RedirectResponse
    96|    {
    97|        $ingredient->delete();
    98|
    99|        return back()->with('success', 'Bahan dihapus.');
   100|    }
   101|
   102|    private function isOwner(): bool
   103|    {
   104|        return (bool) request()->user()?->isOwner();
   105|    }
   106|}
   107|