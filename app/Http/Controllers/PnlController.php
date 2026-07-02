<?php

namespace App\Http\Controllers;

use App\Exports\PnlExport;
use App\Services\PnlService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PnlController extends Controller
{
    public function index(Request $request, PnlService $pnl): Response
    {
        [$month, $year] = $this->period($request);
        $branchId = $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;

        $branches = \App\Models\Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Reports/Pnl', [
            'report' => $pnl->report($month, $year, $branchId),
            'period' => ['month' => $month, 'year' => $year],
            'periodLabel' => Carbon::create($year, $month)->translatedFormat('F Y'),
            'branchId' => $branchId,
            'branches' => $branches,
        ]);
    }

    public function export(Request $request, PnlService $pnl, PnlExport $export): StreamedResponse
    {
        [$month, $year] = $this->period($request);
        $branchId = $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
        $report = $pnl->report($month, $year, $branchId);
        $label = Carbon::create($year, $month)->translatedFormat('F Y');

        return $export->download($report, $label);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();
        $month = (int) $request->integer('month', $now->month);
        $year = (int) $request->integer('year', $now->year);

        $month = max(1, min(12, $month));

        return [$month, $year];
    }
}
