<?php

use App\Http\Controllers\Api\BotAuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/bot/validate-token', [BotAuthController::class, 'validateToken'])
    ->middleware('throttle:bot');

Route::middleware(['bot.token', 'throttle:bot'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/reports/today', [ReportController::class, 'today']);
    Route::get('/reports/aging', [ReportController::class, 'aging']);

    Route::get('/partners', [PartnerController::class, 'index']);
    Route::post('/partners', [PartnerController::class, 'store']);
    Route::get('/partners/{partner}', [PartnerController::class, 'show']);
    Route::put('/partners/{partner}', [PartnerController::class, 'update']);
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy']);
    Route::get('/partners/{partner}/aging', [PartnerController::class, 'aging']);

    Route::get('/invoices/outstanding', [InvoiceController::class, 'outstanding']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
});
