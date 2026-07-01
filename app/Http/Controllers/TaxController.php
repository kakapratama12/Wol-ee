<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaxSimulateRequest;
use App\Models\Expense;
use App\Models\Sale;
use App\Services\TaxSimulatorService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tax/Simulator', [
            'defaults' => $this->yearToDateDefaults(),
            'result' => null,
        ]);
    }

    public function simulate(TaxSimulateRequest $request, TaxSimulatorService $tax): Response
    {
        $data = $request->validated();

        $result = $tax->simulate(
            businessType: $data['business_type'],
            omset: (float) $data['omset'],
            cogs: (float) $data['cogs'],
            expense: (float) $data['expense'],
            wastePercent: (float) $data['waste_percent'],
        );

        return Inertia::render('Tax/Simulator', [
            'defaults' => $data,
            'result' => $result,
        ]);
    }

    /**
     * Estimasi omset, COGS, dan expense tahun berjalan dari data tracking.
     *
     * @return array<string, mixed>
     */
    private function yearToDateDefaults(): array
    {
        $year = Carbon::now()->year;

        $omset = (float) Sale::query()->active()->whereYear('occurred_at', $year)->sum('revenue');
        $cogs = (float) Sale::query()->active()->whereYear('occurred_at', $year)->sum('cogs');
        $expense = (float) Expense::where('period_year', $year)->sum('amount');

        return [
            'business_type' => 'perorangan',
            'omset' => round($omset, 2),
            'cogs' => round($cogs, 2),
            'expense' => round($expense, 2),
            'waste_percent' => 10,
        ];
    }
}
