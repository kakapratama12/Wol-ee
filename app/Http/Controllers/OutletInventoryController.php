<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletInventoryController extends Controller
{
    /**
     * Show inventory for a specific outlet.
     */
    public function index(Request $request, $outletId)
    {
        $outlet = Outlet::findOrFail($outletId);

        $inventory = $outlet->inventory()->with(['product', 'ingredient'])->get();

        $products = Product::all();
        $ingredients = Ingredient::all();

        return Inertia::render('Outlets/Inventory', [
            'outlet' => $outlet,
            'inventory' => $inventory,
            'products' => $products,
            'ingredients' => $ingredients,
        ]);
    }

    /**
     * Manual stock adjustment.
     */
    public function adjust(Request $request, $outletId)
    {
        $validated = $request->validate([
            'item_id' => 'required|numeric',
            'item_source' => 'required|in:ingredient,product',
            'adjustment' => 'required|numeric',
            'unit' => 'required|string',
        ]);

        DB::transaction(function () use ($outletId, $validated) {
            $isIngredient = $validated['item_source'] === 'ingredient';
            
            // Find or create inventory record
            $inventory = OutletInventory::firstOrCreate(
                [
                    'outlet_id' => $outletId,
                    'product_id' => $isIngredient ? null : $validated['item_id'],
                    'ingredient_id' => $isIngredient ? $validated['item_id'] : null,
                ],
                [
                    'quantity' => 0,
                    'unit' => $validated['unit'],
                ]
            );

            $inventory->update([
                'quantity' => DB::raw("quantity + {$validated['adjustment']}"),
                'unit' => $validated['unit'],
                'last_updated' => now(),
            ]);
        });

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }
}
