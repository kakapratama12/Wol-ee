<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\OutletInventory;
use App\Models\Sale;
use App\Models\Transaction;
use App\Services\PnlService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, PnlService $pnl): Response
    {
        $user = $request->user();
        $now = Carbon::now();

        // Staff view: simplified dashboard scoped to their outlet
        if ($user->isStaff()) {
            return $this->staffDashboard($request, $user, $now);
        }

        // Pengelola / admin view: full dashboard
        return $this->pengelolaDashboard($request, $pnl, $now);
    }

    private function staffDashboard(Request $request, $user, Carbon $now): Response
    {
        $outletId = $user->outlet_id;
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        // Today's sales for this outlet
        $todaySales = Sale::query()
            ->active()
            ->with('product:id,name')
            ->where('outlet_id', $outletId)
            ->whereBetween('occurred_at', [$todayStart->toIso8601String(), $todayEnd->toIso8601String()])
            ->latest('occurred_at')
            ->get();

        $todayRevenue = round((float) $todaySales->sum('revenue'), 2);
        $todayTransactions = $todaySales->count();
        $todayItemsSold = (int) $todaySales->sum('quantity');

        $recentSales = $todaySales
            ->take(10)
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'product' => $s->product?->name,
                'quantity' => $s->quantity,
                'revenue' => (float) $s->revenue,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ]);

        // Outlet inventory
        $outletInventory = \App\Models\OutletInventory::query()
            ->with('ingredient:id,name,base_unit,minimum_stock')
            ->where('outlet_id', $outletId)
            ->orderBy('ingredient_id')
            ->get()
            ->map(fn (\App\Models\OutletInventory $oi) => [
                'id' => $oi->id,
                'ingredient_name' => $oi->ingredient?->name ?? '-',
                'quantity' => (float) $oi->quantity,
                'unit' => $oi->unit,
                'base_unit' => $oi->ingredient?->base_unit ?? $oi->unit,
                'minimum_stock' => $oi->ingredient ? (float) $oi->ingredient->minimum_stock : 0,
            ]);

        $outletName = $user->outlet?->name ?? '';

        return Inertia::render('Dashboard', [
            'isStaff' => true,
            'outletName' => $outletName,
            'todayRevenue' => $todayRevenue,
            'todayTransactions' => $todayTransactions,
            'todayItemsSold' => $todayItemsSold,
            'recentSales' => $recentSales,
            'outletInventory' => $outletInventory,
            // Pass empty defaults so the pengelola props are not required
            'period' => 'today',
            'periodLabel' => $now->translatedFormat('d M Y'),
            'startDate' => $todayStart->toDateString(),
            'endDate' => $todayEnd->toDateString(),
            'metrics' => ['revenue' => 0, 'cogs' => 0, 'gross_profit' => 0, 'gross_margin' => 0, 'net_profit' => 0],
            'lowStock' => [],
            'recentPurchases' => [],
            'monthlyChart' => [],
            'upcomingPayables' => [],
        ]);
    }

    private function pengelolaDashboard(Request $request, PnlService $pnl, Carbon $now): Response
    {
        $period = $request->input('period', 'this_month');
        $outletId = $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null;

        if ($outletId !== null) {
            abort_unless(
                Outlet::query()->where('id', $outletId)->exists(),
                404,
            );
        }

        // Determine date range based on period
        switch ($period) {
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $label = $start->translatedFormat('d M') . ' – ' . $end->translatedFormat('d M Y');
                break;
            case 'last_3_months':
                $start = $now->copy()->subMonths(3)->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = $start->translatedFormat('M Y') . ' – ' . $end->translatedFormat('M Y');
                break;
            case 'custom':
                $start = $request->input('start_date')
                    ? Carbon::parse($request->input('start_date'))
                    : $now->copy()->startOfMonth();
                $end = $request->input('end_date')
                    ? Carbon::parse($request->input('end_date'))
                    : $now->copy()->endOfMonth();
                $label = $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y');
                break;
            default: // this_month
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = $now->translatedFormat('F Y');
                break;
        }

        // PnL report (monthly, for overview metrics)
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
            ->active()
            ->with('product:id,name')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereBetween('occurred_at', [$start->toDateString(), $end->endOfDay()->toIso8601String()])
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
            ->whereBetween('occurred_at', [$start->toDateString(), $end->endOfDay()->toIso8601String()])
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

        // Metrics for selected period
        $periodRevenue = round((float) Sale::query()
            ->active()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereBetween('occurred_at', [$start->toDateString(), $end->endOfDay()->toIso8601String()])
            ->sum('revenue'), 2);

        $periodCogs = round((float) Sale::query()
            ->active()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereBetween('occurred_at', [$start->toDateString(), $end->endOfDay()->toIso8601String()])
            ->sum('cogs'), 2);

        $periodExpenses = round((float) Expense::query()
            ->where('period_year', $now->year)
            ->where('period_month', $now->month)
            ->sum('amount'), 2);

        $grossProfit = round($periodRevenue - $periodCogs, 2);
        $grossMargin = $periodRevenue > 0 ? round(($grossProfit / $periodRevenue) * 100, 2) : 0.0;
        $netProfit = round($grossProfit - $periodExpenses, 2);

        $monthlyChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($now, $outletId) {
                $period = $now->copy()->subMonths($monthsAgo);
                $month = $period->month;
                $year = $period->year;

                return [
                    'label' => $period->translatedFormat('M Y'),
                    'month' => $month,
                    'year' => $year,
                    'revenue' => round((float) Sale::query()
                        ->active()
                        ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                        ->whereYear('occurred_at', $year)
                        ->whereMonth('occurred_at', $month)
                        ->sum('revenue'), 2),
                    'expense' => round((float) Expense::query()
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->sum('amount'), 2),
                ];
            });

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dashboard', [
            'isStaff' => false,
            'period' => $period,
            'periodLabel' => $label,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'outletId' => $outletId,
            'outlets' => $outlets,
            'metrics' => [
                'revenue' => $periodRevenue,
                'cogs' => $periodCogs,
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'net_profit' => $netProfit,
            ],
            'lowStock' => $lowStock,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'monthlyChart' => $monthlyChart,
            'upcomingPayables' => \App\Models\Payable::query()
                ->with('partner:id,name')
                ->whereIn('status', ['outstanding', 'partial'])
                ->where('due_date', '<=', $now->copy()->addDays(30)->toDateString())
                ->orderBy('due_date')
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'payable_number' => $p->payable_number,
                    'partner' => $p->partner?->name,
                    'remaining' => round((float) $p->amount - (float) $p->paid_amount, 2),
                    'due_date' => $p->due_date?->toDateString(),
                ]),
        ]);
    }
}
