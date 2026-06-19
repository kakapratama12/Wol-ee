<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\Transaction;
use App\Services\PnlService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(PnlService $pnl): Response
    {
        $now = Carbon::now();
        $report = $pnl->report($now->month, $now->year);

        $lowStock = Ingredient::query()
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'base_unit' => $i->base_unit,
                'status' => $i->stock_status,
            ]);

        $recentSales = Sale::query()
            ->with('product:id,name')
            ->latest('occurred_at')
            ->take(8)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'product' => $s->product?->name,
                'quantity' => $s->quantity,
                'revenue' => (float) $s->revenue,
                'profit' => (float) $s->profit,
                'margin' => (float) $s->margin,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ]);

        $recentPurchases = Transaction::query()
            ->with('ingredient:id,name,base_unit')
            ->latest('occurred_at')
            ->take(8)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'ingredient' => $t->ingredient?->name,
                'base_unit' => $t->ingredient?->base_unit,
                'quantity' => (float) $t->quantity,
                'total' => (float) $t->total,
                'source' => $t->source,
                'occurred_at' => $t->occurred_at?->toIso8601String(),
            ]);

        $monthlyChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($now) {
                $period = $now->copy()->subMonths($monthsAgo);
                $month = $period->month;
                $year = $period->year;

                return [
                    'label' => $period->translatedFormat('M Y'),
                    'month' => $month,
                    'year' => $year,
                    'revenue' => round((float) Sale::query()
                        ->whereYear('occurred_at', $year)
                        ->whereMonth('occurred_at', $month)
                        ->sum('revenue'), 2),
                    'expense' => round((float) Expense::query()
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->sum('amount'), 2),
                ];
            });

        return Inertia::render('Dashboard', [
            'month' => $now->translatedFormat('F Y'),
            'metrics' => [
                'revenue' => $report['revenue'],
                'cogs' => $report['cogs'],
                'gross_profit' => $report['gross_profit'],
                'gross_margin' => $report['gross_margin'],
                'net_profit' => $report['net_profit'],
            ],
            'lowStock' => $lowStock,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'monthlyChart' => $monthlyChart,
        ]);
    }
}
