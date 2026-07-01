<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::withCount('inventory')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Outlets/Index', [
            'outlets' => $outlets,
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
