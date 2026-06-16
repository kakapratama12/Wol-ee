<?php

use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:bot'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Endpoint untuk bot Telegram (Python).
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/reports/today', [ReportController::class, 'today']);
});
