<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\DistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DistributionController extends Controller
{
    public function __construct(
        protected DistributionService $distributionService
    ) {}

    public function index()
    {
        $distributions = Distribution::with(['fromOutlet', 'toOutlet', 'items.product', 'items.ingredient'])
            ->orderByDesc('distributed_at')
            ->get();

        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();
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
        // Convert empty string to null for Gudang Pusat
        if ($request->input('from_outlet_id') === '') {
            $request->merge(['from_outlet_id' => null]);
        }

        $validated = $request->validate([
            'from_outlet_id' => 'nullable',
            'to_outlet_id' => 'required|exists:outlets,id',
            'distributed_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_source' => 'required|in:ingredient,product',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
        ]);

        // Validate from_outlet exists only when provided
        if (!empty($validated['from_outlet_id']) && !Outlet::find($validated['from_outlet_id'])) {
            return back()->withErrors(['from_outlet_id' => 'Outlet asal tidak ditemukan.']);
        }

        // Validate: from and to must be different when from_outlet_id is set
        if (isset($validated['from_outlet_id']) && $validated['from_outlet_id'] == $validated['to_outlet_id']) {
            return back()->withErrors(['from_outlet_id' => 'Outlet asal dan tujuan tidak boleh sama.']);
        }

        DB::transaction(function () use ($validated) {
            $distribution = Distribution::create([
                'from_outlet_id' => $validated['from_outlet_id'] ?? null,
                'to_outlet_id' => $validated['to_outlet_id'],
                'distributed_at' => $validated['distributed_at'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->distributionService->applyItems($distribution, $validated['items'], $validated['to_outlet_id'], $validated['from_outlet_id'] ?? null);
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
        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();
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
        // Convert empty string to null for Gudang Pusat
        if ($request->input('from_outlet_id') === '') {
            $request->merge(['from_outlet_id' => null]);
        }

        $validated = $request->validate([
            'from_outlet_id' => 'nullable',
            'to_outlet_id' => 'required|exists:outlets,id',
            'distributed_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_source' => 'required|in:ingredient,product',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
        ]);

        // Validate from_outlet exists only when provided
        if (!empty($validated['from_outlet_id']) && !Outlet::find($validated['from_outlet_id'])) {
            return back()->withErrors(['from_outlet_id' => 'Outlet asal tidak ditemukan.']);
        }

        if (isset($validated['from_outlet_id']) && $validated['from_outlet_id'] == $validated['to_outlet_id']) {
            return back()->withErrors(['from_outlet_id' => 'Outlet asal dan tujuan tidak boleh sama.']);
        }

        DB::transaction(function () use ($validated, $id) {
            $distribution = Distribution::with('items')->findOrFail($id);

            // Reverse old inventory changes
            $this->distributionService->reverseItems($distribution->items, $distribution->to_outlet_id, $distribution->from_outlet_id);

            // Delete old distribution items
            $distribution->items()->delete();

            // Create new items and apply new inventory changes
            $this->distributionService->applyItems($distribution, $validated['items'], $validated['to_outlet_id'], $validated['from_outlet_id'] ?? null);

            // Update distribution fields
            $distribution->update([
                'from_outlet_id' => $validated['from_outlet_id'] ?? null,
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
            $this->distributionService->reverseItems($distribution->items, $distribution->to_outlet_id, $distribution->from_outlet_id);

            // Delete distribution items and distribution
            $distribution->items()->delete();
            $distribution->delete();
        });

        return redirect()->route('distributions.index')
            ->with('success', 'Distribusi berhasil dihapus.');
    }
}
