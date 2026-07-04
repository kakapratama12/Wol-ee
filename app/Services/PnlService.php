<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use App\Support\CalculationHelper;
use Illuminate\Database\Eloquent\Builder;

class PnlService
{
    /**
     * Laporan P&L untuk satu bulan dengan breakdown detail.
     *
     * @return array<string, mixed>
     */
    public function report(int $month, int $year, ?int $branchId = null): array
    {
        $saleQuery = fn (): Builder => $this->salesForPeriod($month, $year, $branchId);

        $revenueByProduct = $saleQuery()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('products.name as product, SUM(sales.revenue) as revenue')
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();

        $cogsByProduct = $saleQuery()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('products.name as product, SUM(sales.cogs) as cogs')
            ->groupBy('products.name')
            ->orderByDesc('cogs')
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'cogs' => round((float) $row->cogs, 2),
            ])
            ->all();

        $revenue = round(array_sum(array_column($revenueByProduct, 'revenue')), 2);
        $cogs = round(array_sum(array_column($cogsByProduct, 'cogs')), 2);
        $grossProfit = round($revenue - $cogs, 2);

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
            'branch_id' => $branchId,
            'revenue' => $revenue,
            'revenue_by_product' => $revenueByProduct,
            'cogs' => $cogs,
            'cogs_by_product' => $cogsByProduct,
            'gross_profit' => $grossProfit,
            'gross_margin' => CalculationHelper::marginPercent($revenue, $cogs),
            'expenses' => $expenseItems,
            'total_expenses' => $totalExpenses,
            'expenses_by_category' => $expenseByCategory,
            'net_profit' => $netProfit,
            'net_margin' => CalculationHelper::marginPercent($revenue, $cogs + $totalExpenses),
        ];
    }

    private function salesForPeriod(int $month, int $year, ?int $branchId): Builder
    {
        $query = Sale::query()
            ->active()
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $month);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }
}
