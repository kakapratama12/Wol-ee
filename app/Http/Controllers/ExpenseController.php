<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
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
            ]);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'total' => round((float) $expenses->sum('amount'), 2),
            'categories' => Expense::CATEGORIES,
            'categoryDescriptions' => Expense::CATEGORY_DESCRIPTIONS,
            'period' => ['month' => $month, 'year' => $year],
            'periodLabel' => Carbon::create($year, $month)->translatedFormat('F Y'),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['occurred_at'] = $request->date('occurred_at') ?? now();
        Expense::create($data);

        return back()->with('success', 'Biaya ditambahkan.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();
        if ($request->has('occurred_at')) {
            $data['occurred_at'] = $request->date('occurred_at');
        }
        $expense->update($data);

        return back()->with('success', 'Biaya diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Biaya dihapus.');
    }
}
