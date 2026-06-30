<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;

class PnlService
{
    /**
     * Laporan P&L untuk satu bulan dengan breakdown detail.
     *
     * @return array<string, mixed>
     */
    public function report(int $month, int $year): array
    {
        // Revenue breakdown per product
        $revenueByProduct = Sale::query()
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $month)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('products.name as product, SUM(sales.revenue) as revenue, SUM(sales.quantity) as quantity')
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'revenue' => round((float) $row->revenue, 2),
                'quantity' => (int) $row->quantity,
            ])
            ->all();

        // COGS breakdown per product
        $cogsByProduct = Sale::query()
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $month)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('products.name as product, SUM(sales.cogs) as cogs, SUM(sales.quantity) as quantity')
            ->groupBy('products.name')
            ->orderByDesc('cogs')
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'cogs' => round((float) $row->cogs, 2),
                'quantity' => (int) $row->quantity,
            ])
            ->all();

        $revenue = round(array_sum(array_column($revenueByProduct, 'revenue')), 2);
        $cogs = round(array_sum(array_column($cogsByProduct, 'cogs')), 2);
        $grossProfit = round($revenue - $cogs, 2);

        // Expense breakdown per item
        $expenseItems = Expense::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('category', Expense::PNL_CATEGORIES)
            ->selectRaw('category, description, amount')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'description' => $row->description ?? '-',
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();

        // Expense by category
        $expenseByCategory = Expense::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('category', Expense::PNL_CATEGORIES)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->category => round((float) $row->total, 2)])
            ->all();

        $totalExpenses = round(array_sum($expenseByCategory), 2);
        $netProfit = round($grossProfit - $totalExpenses, 2);

        return [
            'month' => $month,
            'year' => $year,
            'revenue' => $revenue,
            'revenue_by_product' => $revenueByProduct,
            'cogs' => $cogs,
            'cogs_by_product' => $cogsByProduct,
            'gross_profit' => $grossProfit,
            'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0,
            'expenses' => $expenseItems,
            'total_expenses' => $totalExpenses,
            'expenses_by_category' => $expenseByCategory,
            'net_profit' => $netProfit,
            'net_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0.0,
        ];
    }
}
