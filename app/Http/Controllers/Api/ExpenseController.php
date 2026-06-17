<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBotExpenseRequest;
use App\Http\Support\ApiResponse;
use App\Models\Expense;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function store(StoreBotExpenseRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $expense = Expense::create(array_merge($request->validated(), [
            'tenant_id' => $tenant->id,
        ]));

        return ApiResponse::success('Biaya dicatat.', [
            'id' => $expense->id,
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'period_month' => $expense->period_month,
            'period_year' => $expense->period_year,
        ], 201);
    }
}
