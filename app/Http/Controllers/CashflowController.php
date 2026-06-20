<?php

namespace App\Http\Controllers;

use App\Services\CashflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CashflowController extends Controller
{
    public function index(Request $request, CashflowService $cashflow): Response
    {
        $now = Carbon::now();
        $month = max(1, min(12, (int) $request->integer('month', $now->month)));
        $year = (int) $request->integer('year', $now->year);

        return Inertia::render('Reports/Cashflow', [
            'report' => $cashflow->report($month, $year),
            'period' => ['month' => $month, 'year' => $year],
            'periodLabel' => Carbon::create($year, $month)->translatedFormat('F Y'),
        ]);
    }
}
