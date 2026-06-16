<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Sale;
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
        ]);
    }
}
