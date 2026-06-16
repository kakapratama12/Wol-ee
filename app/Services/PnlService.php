<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;

class PnlService
{
    /**
     * Laporan P&L untuk satu bulan.
     *
     * @return array<string, mixed>
     */
    public function report(int $month, int $year): array
    {
        $revenue = (float) Sale::query()
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $month)
            ->sum('revenue');

        $cogs = (float) Sale::query()
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $month)
            ->sum('cogs');

        $grossProfit = round($revenue - $cogs, 2);

        $expenseRows = Expense::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'amount' => round((float) $row->total, 2),
            ])
            ->all();

        $totalExpenses = round(array_sum(array_column($expenseRows, 'amount')), 2);
        $netProfit = round($grossProfit - $totalExpenses, 2);

        return [
            'month' => $month,
            'year' => $year,
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => $grossProfit,
            'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0,
            'expenses' => $expenseRows,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0.0,
        ];
    }
}
