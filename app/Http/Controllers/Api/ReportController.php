<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Sale;
use App\Services\AgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(private readonly AgingService $aging) {}

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

    public function aging(): JsonResponse
    {
        return ApiResponse::success('Laporan aging.', $this->aging->report());
    }
}
