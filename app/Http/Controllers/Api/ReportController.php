<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Services\AgingService;
use App\Services\MarginService;
use App\Services\PnlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private readonly AgingService $aging,
        private readonly PnlService $pnl,
        private readonly MarginService $margin,
    ) {}

    public function today(): JsonResponse
    {
        $today = Carbon::today();

        $sales = Sale::query()->whereDate('occurred_at', $today);

        $revenue = (float) $sales->clone()->sum('revenue');
        $cogs = (float) $sales->clone()->sum('cogs');
        $profit = round($revenue - $cogs, 2);
        $count = $sales->clone()->count();

        return ApiResponse::success('Laporan hari ini.', [
            'date' => $today->toDateString(),
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => $profit,
            'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0,
            'transactions' => $count,
        ]);
    }

    public function pnl(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $month = $request->integer('month', $now->month);
        $year = $request->integer('year', $now->year);

        if ($month < 1 || $month > 12) {
            return ApiResponse::error('Bulan tidak valid.', 'VALIDATION_ERROR', 422);
        }

        if ($year < 2000 || $year > 2100) {
            return ApiResponse::error('Tahun tidak valid.', 'VALIDATION_ERROR', 422);
        }

        $report = $this->pnl->report($month, $year);
        $report['period_label'] = Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y');

        return ApiResponse::success('Laporan P&L.', $report);
    }

    public function stockAlerts(): JsonResponse
    {
        $items = Ingredient::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Ingredient $i) => in_array($i->stock_status, [
                Ingredient::STATUS_LOW,
                Ingredient::STATUS_CRITICAL,
            ], true))
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'ingredient' => $i->name,
                'current_stock' => (float) $i->current_stock,
                'minimum_stock' => (float) $i->minimum_stock,
                'unit' => $i->base_unit,
                'status' => $i->stock_status,
            ])
            ->values()
            ->all();

        $safeCount = Ingredient::query()->get()->filter(
            fn (Ingredient $i) => $i->stock_status === Ingredient::STATUS_SAFE
        )->count();

        return ApiResponse::success('Alert stok.', [
            'alerts' => $items,
            'alert_count' => count($items),
            'safe_count' => $safeCount,
        ]);
    }

    public function marginAlerts(): JsonResponse
    {
        $alerts = $this->margin->alerts()->values()->all();

        return ApiResponse::success('Alert margin.', [
            'alerts' => $alerts,
            'alert_count' => count($alerts),
        ]);
    }

    public function aging(): JsonResponse
    {
        return ApiResponse::success('Laporan aging.', $this->aging->report());
    }
}
