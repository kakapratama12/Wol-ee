<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DistributionController extends Controller
{
    public function index()
    {
        $distributions = Distribution::with(['fromOutlet', 'toOutlet', 'items.product', 'items.ingredient'])
            ->orderByDesc('distributed_at')
            ->get();

        $outlets = Outlet::where('is_active', true)->orderBy('type')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return Inertia::render('Distributions/Index', [
            'distributions' => $distributions,
            'outlets' => $outlets,
            'products' => $products,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
            'distributed_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_source' => 'required|in:ingredient,product',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated) {
            $distribution = Distribution::create([
                'from_outlet_id' => $validated['from_outlet_id'],
                'to_outlet_id' => $validated['to_outlet_id'],
                'distributed_at' => $validated['distributed_at'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $isIngredient = $item['item_source'] === 'ingredient';

                DistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'product_id' => $isIngredient ? null : $item['item_id'],
                    'ingredient_id' => $isIngredient ? $item['item_id'] : null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);

                if ($isIngredient) {
                    // Deduct from source ingredient stock
                    $ingredient = Ingredient::findOrFail($item['item_id']);
                    $ingredient->decrement('current_stock', $item['quantity']);

                    // Add to outlet ingredient inventory
                    OutletInventory::updateOrCreate(
                        [
                            'outlet_id' => $validated['to_outlet_id'],
                            'ingredient_id' => $item['item_id'],
                            'product_id' => null,
                        ],
                        [
                            'quantity' => DB::raw("quantity + {$item['quantity']}"),
                            'unit' => $item['unit'],
                            'last_updated' => now(),
                        ]
                    );
                } else {
                    // Product distribution
                    OutletInventory::updateOrCreate(
                        [
                            'outlet_id' => $validated['to_outlet_id'],
                            'product_id' => $item['item_id'],
                            'ingredient_id' => null,
                        ],
                        [
                            'quantity' => DB::raw("quantity + {$item['quantity']}"),
                            'unit' => $item['unit'],
                            'last_updated' => now(),
                        ]
                    );
                }
            }
        });

        return redirect()->route('distributions.index')
            ->with('success', 'Distribusi berhasil dicatat.');
    }

    public function show($id)
    {
        $distribution = Distribution::with(['fromOutlet', 'toOutlet', 'items.product', 'items.ingredient', 'creator'])
            ->findOrFail($id);

        return Inertia::render('Distributions/Show', [
            'distribution' => $distribution,
        ]);
    }

    public function edit($id)
    {
        $distribution = Distribution::with(['items'])->findOrFail($id);
        $outlets = Outlet::where('is_active', true)->orderBy('type')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return Inertia::render('Distributions/Edit', [
            'distribution' => $distribution,
            'outlets' => $outlets,
            'products' => $products,
            'ingredients' => $ingredients,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
            'distributed_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_source' => 'required|in:ingredient,product',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated, $id) {
            $distribution = Distribution::with('items')->findOrFail($id);

            // Reverse old inventory changes
            foreach ($distribution->items as $oldItem) {
                $isIngredient = $oldItem->ingredient_id !== null;

                if ($isIngredient) {
                    // Reverse: increment source ingredient stock back
                    $ingredient = Ingredient::findOrFail($oldItem->ingredient_id);
                    $ingredient->increment('current_stock', $oldItem->quantity);

                    // Reverse: deduct from destination outlet ingredient inventory
                    OutletInventory::where([
                        'outlet_id' => $distribution->to_outlet_id,
                        'ingredient_id' => $oldItem->ingredient_id,
                        'product_id' => null,
                    ])->decrement('quantity', $oldItem->quantity);
                } else {
                    // Reverse: deduct from destination outlet product inventory
                    OutletInventory::where([
                        'outlet_id' => $distribution->to_outlet_id,
                        'product_id' => $oldItem->product_id,
                        'ingredient_id' => null,
                    ])->decrement('quantity', $oldItem->quantity);
                }
            }

            // Delete old distribution items
            $distribution->items()->delete();

            // Create new items and apply new inventory changes
            foreach ($validated['items'] as $item) {
                $isIngredient = $item['item_source'] === 'ingredient';

                DistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'product_id' => $isIngredient ? null : $item['item_id'],
                    'ingredient_id' => $isIngredient ? $item['item_id'] : null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);

                if ($isIngredient) {
                    // Deduct from source ingredient stock
                    $ingredient = Ingredient::findOrFail($item['item_id']);
                    $ingredient->decrement('current_stock', $item['quantity']);

                    // Add to outlet ingredient inventory
                    OutletInventory::updateOrCreate(
                        [
                            'outlet_id' => $validated['to_outlet_id'],
                            'ingredient_id' => $item['item_id'],
                            'product_id' => null,
                        ],
                        [
                            'quantity' => DB::raw("quantity + {$item['quantity']}"),
                            'unit' => $item['unit'],
                            'last_updated' => now(),
                        ]
                    );
                } else {
                    // Product distribution — add to outlet product inventory
                    OutletInventory::updateOrCreate(
                        [
                            'outlet_id' => $validated['to_outlet_id'],
                            'product_id' => $item['item_id'],
                            'ingredient_id' => null,
                        ],
                        [
                            'quantity' => DB::raw("quantity + {$item['quantity']}"),
                            'unit' => $item['unit'],
                            'last_updated' => now(),
                        ]
                    );
                }
            }

            // Update distribution fields
            $distribution->update([
                'from_outlet_id' => $validated['from_outlet_id'],
                'to_outlet_id' => $validated['to_outlet_id'],
                'distributed_at' => $validated['distributed_at'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->route('distributions.index')
            ->with('success', 'Distribusi berhasil diperbarui.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $distribution = Distribution::with('items')->findOrFail($id);

            // Reverse inventory changes for each item
            foreach ($distribution->items as $item) {
                $isIngredient = $item->ingredient_id !== null;

                if ($isIngredient) {
                    // Reverse: increment source ingredient stock back
                    $ingredient = Ingredient::findOrFail($item->ingredient_id);
                    $ingredient->increment('current_stock', $item->quantity);

                    // Reverse: deduct from destination outlet ingredient inventory
                    OutletInventory::where([
                        'outlet_id' => $distribution->to_outlet_id,
                        'ingredient_id' => $item->ingredient_id,
                        'product_id' => null,
                    ])->decrement('quantity', $item->quantity);
                } else {
                    // Reverse: deduct from destination outlet product inventory
                    OutletInventory::where([
                        'outlet_id' => $distribution->to_outlet_id,
                        'product_id' => $item->product_id,
                        'ingredient_id' => null,
                    ])->decrement('quantity', $item->quantity);
                }
            }

            // Delete distribution items and distribution
            $distribution->items()->delete();
            $distribution->delete();
        });

        return redirect()->route('distributions.index')
            ->with('success', 'Distribusi berhasil dihapus.');
    }
}
