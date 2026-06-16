<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function today(): JsonResponse
    {
        $today = Carbon::today();

        $sales = Sale::query()->whereDate('occurred_at', $today);

        $revenue = (float) $sales->clone()->sum('revenue');
        $cogs = (float) $sales->clone()->sum('cogs');
        $profit = round($revenue - $cogs, 2);
        $count = $sales->clone()->count();

        return response()->json([
            'date' => $today->toDateString(),
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => $profit,
            'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0,
            'transactions' => $count,
        ]);
    }
}
