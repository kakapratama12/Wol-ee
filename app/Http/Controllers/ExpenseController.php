<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $now = Carbon::now();
        $month = max(1, min(12, (int) $request->integer('month', $now->month)));
        $year = (int) $request->integer('year', $now->year);

        $expenses = Expense::query()
            ->with('outlet:id,name')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->latest()
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'period_month' => $e->period_month,
                'period_year' => $e->period_year,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'outlet_id' => $e->outlet_id,
                'outlet_name' => $e->outlet?->name,
            ]);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'total' => round((float) $expenses->sum('amount'), 2),
            'categories' => Expense::CATEGORIES,
            'categoryDescriptions' => Expense::CATEGORY_DESCRIPTIONS,
            'outlets' => Outlet::select('id', 'name')->orderBy('name')->get(),
            'period' => ['month' => $month, 'year' => $year],
            'periodLabel' => Carbon::create($year, $month)->translatedFormat('F Y'),
            'businessType' => auth()->user()->tenant?->business_type ?? 'single',
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['occurred_at'] = $request->date('occurred_at') ?? now();

        // Use provided outlet_id, or auto-assign from user's outlet
        $data['outlet_id'] = $request->input('outlet_id') ?? auth()->user()->outlet_id;

        Expense::create($data);

        return back()->with('success', 'Biaya ditambahkan.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $data = $request->validated();
            if ($request->has('occurred_at')) {
                $data['occurred_at'] = $request->date('occurred_at');
            }
            $expense->update($data);
            return back()->with('success', 'Biaya diperbarui.');
        } catch (\Throwable $e) {
            \Log::error('Expense update failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Gagal memperbarui biaya.']);
        }
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        try {
            $expense->delete();
            return back()->with('success', 'Biaya dihapus.');
        } catch (\Throwable $e) {
            \Log::error('Expense delete failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Gagal menghapus biaya.']);
        }
    }
}
