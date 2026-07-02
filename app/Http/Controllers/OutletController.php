<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\OutletStockService;
use Inertia\Inertia;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::where('is_active', true)->withCount('inventory')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Outlets/Index', [
            'outlets' => $outlets,
        ]);
    }


    public function show(Outlet $outlet)
    {
        $outlet->loadCount('inventory');
        $inventory = $outlet->inventory()->with(['product', 'ingredient'])->get();

        $stockService = app(OutletStockService::class);
        $movements = $stockService->getMovements($outlet);
        $mappedMovements = $movements->map(fn ($m) => [
            'id' => $m->id,
            'ingredient' => $m->ingredient?->name,
            'type' => $m->type,
            'quantity' => (float) $m->quantity,
            'stock_after' => (float) $m->stock_after,
            'reason' => $m->reason,
            'note' => $m->note,
            'user' => $m->user?->name,
            'occurred_at' => $m->occurred_at?->format('d M Y H:i'),
        ]);

        $products = Product::all();
        $ingredients = Ingredient::all();

        return Inertia::render('Outlets/Show', [
            'outlet' => $outlet,
            'inventory' => $inventory,
            'movements' => $mappedMovements,
            'products' => $products,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:pusat,outlet',
            'address' => 'nullable|string|max:500',
        ]);

        Outlet::create($validated);

        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil ditambahkan.');
    }

    public function update(Request $request, Outlet $outlet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:pusat,outlet',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $outlet->update($validated);

        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil diupdate.');
    }

    public function destroy(Outlet $outlet)
    {
        $outlet->update(['is_active' => false]);

        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil dinonaktifkan.');
    }
}
