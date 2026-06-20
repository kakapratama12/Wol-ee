<?php

namespace App\Http\Controllers;

use App\Models\CashEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(CashEntry::TYPES))],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated) {
            CashEntry::create([
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'occurred_at' => $validated['occurred_at'],
            ]);
        });

        return back()->with('success', 'Kas masuk tercatat.');
    }

    public function destroy(CashEntry $cashEntry): RedirectResponse
    {
        $cashEntry->delete();

        return back()->with('success', 'Kas masuk dihapus.');
    }
}
