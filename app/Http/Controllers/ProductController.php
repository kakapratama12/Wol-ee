     1|<?php
     2|
     3|namespace App\Http\Controllers;
     4|
     5|use App\Http\Requests\StoreProductRequest;
     6|use App\Http\Requests\UpdateProductRequest;
     7|use App\Http\Requests\UpdateRecipeRequest;
     8|use App\Models\Ingredient;
     9|use App\Models\Product;
    10|use App\Models\RecipeItem;
    11|use App\Services\CogsService;
    12|use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
    13|use Illuminate\Support\Facades\DB;
    14|use Inertia\Inertia;
    15|use Inertia\Response;
    16|
    17|class ProductController extends Controller
    18|{
    19|    public function index(CogsService $cogs): Response
    20|    {
    21|        $products = Product::query()
    22|            ->with('recipeItems.ingredient:id,name,base_unit,unit_price')
    23|            ->orderBy('name')
    24|            ->get()
    25|            ->map(function (Product $product) use ($cogs) {
    26|                $cogsValue = $cogs->cogsForProduct($product);
    27|
    28|                return [
    29|                    'id' => $product->id,
    30|                    'name' => $product->name,
    31|                    'unit' => $product->unit,
    32|                    'selling_price' => (float) $product->selling_price,
    33|                    'is_active' => $product->is_active,
    34|                    'cogs' => $cogsValue,
    35|                    'margin' => $cogs->margin($product),
    36|                    'recipe' => $product->recipeItems->map(fn (RecipeItem $item) => [
    37|                        'ingredient_id' => $item->ingredient_id,
    38|                        'ingredient' => $item->ingredient?->name,
    39|                        'base_unit' => $item->ingredient?->base_unit,
    40|                        'unit_price' => (float) ($item->ingredient?->unit_price ?? 0),
    41|                        'quantity' => (float) $item->quantity,
    42|                        'cost' => round((float) $item->quantity * (float) ($item->ingredient?->unit_price ?? 0), 2),
    43|                    ])->values(),
    44|                ];
    45|            });
    46|
    47|        return Inertia::render('Products/Index', [
    48|            'products' => $products,
    49|            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'base_unit', 'unit_price']),
    50|        ]);
    51|    }
    52|
    53|    public function store(StoreProductRequest $request): RedirectResponse
    54|    {
    55|        Product::create($request->validated());
    56|
    57|        return back()->with('success', 'Produk ditambahkan.');
    58|    }
    59|
    60|    public function storeJson(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'selling_price' => (float) $product->selling_price,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    61|    {
    62|        $product->update($request->validated());
    63|
    64|        return back()->with('success', 'Produk diperbarui.');
    65|    }
    66|
    67|    public function destroy(Product $product): RedirectResponse
    68|    {
    69|        $product->delete();
    70|
    71|        return back()->with('success', 'Produk dihapus.');
    72|    }
    73|
    74|    public function updateRecipe(UpdateRecipeRequest $request, Product $product): RedirectResponse
    75|    {
    76|        $items = $request->validated()['items'];
    77|
    78|        DB::transaction(function () use ($product, $items) {
    79|            $product->recipeItems()->delete();
    80|
    81|            foreach ($items as $item) {
    82|                RecipeItem::create([
    83|                    'product_id' => $product->id,
    84|                    'ingredient_id' => $item['ingredient_id'],
    85|                    'quantity' => $item['quantity'],
    86|                ]);
    87|            }
    88|        });
    89|
    90|        return back()->with('success', 'Resep disimpan & COGS diperbarui.');
    91|    }
    92|}
    93|