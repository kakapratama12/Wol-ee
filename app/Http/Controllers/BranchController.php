<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        $branches = Branch::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'address' => $b->address,
                'is_active' => $b->is_active,
                'users_count' => $b->users()->count(),
            ]);

        return Inertia::render('Settings/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        Branch::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Cabang ditambahkan.');
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->tenant_id === $request->user()->tenant_id, 403);

        $branch->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Cabang diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless($branch->tenant_id === auth()->user()->tenant_id, 403);

        if ($branch->users()->exists() || $branch->cashierSessions()->exists()) {
            return back()->withErrors(['error' => 'Cabang tidak bisa dihapus karena masih dipakai.']);
        }

        $branch->delete();

        return back()->with('success', 'Cabang dihapus.');
    }
}
