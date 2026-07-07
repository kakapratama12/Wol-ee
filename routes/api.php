<?php

use App\Http\Controllers\Api\BotAuthController;
use App\Http\Controllers\Api\BotAiRequestController;
use App\Http\Controllers\Api\BotFeedbackController;
use App\Http\Controllers\Api\BotInputController;
use App\Http\Controllers\Api\BotUsageController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MetaSchemaController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ProductionRunController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/bot/validate-token', [BotAuthController::class, 'validateToken'])
    ->middleware('throttle:bot');

Route::middleware(['bot.token', 'throttle:bot'])->group(function () {
    // Meta schema (for bot integration)
    Route::get('/meta/schema', [MetaSchemaController::class, 'schema']);

    Route::get('/bot/usage', [BotUsageController::class, 'show']);
    Route::post('/bot/ai-usage', [BotUsageController::class, 'consume']);
    Route::post('/bot/ai-requests', [BotAiRequestController::class, 'store']);
    Route::post('/bot/feedback', [BotFeedbackController::class, 'store']);

    // Bot Input History
    Route::get('/bot-inputs', [BotInputController::class, 'index']);
    Route::post('/bot-inputs', [BotInputController::class, 'store']);
    Route::put('/bot-inputs/{botInput}/archive', [BotInputController::class, 'archive']);

    // Transactions (Pembelian)
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/transactions/batch', [TransactionController::class, 'storeBatch']);

    // Sales (Penjualan)
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::post('/sales/batch', [SaleController::class, 'storeBatch']);

    // Expenses (Pengeluaran)
    Route::post('/expenses', [ExpenseController::class, 'store']);

    // Ingredients (Bahan Baku)
    Route::get('/ingredients', [IngredientController::class, 'index']);
    Route::post('/ingredients', [IngredientController::class, 'store']);

    // Products (Produk Jadi)
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);

    // Recipes (Resep)
    Route::get('/products/{productId}/recipe', [RecipeController::class, 'show']);
    Route::post('/products/{productId}/recipe', [RecipeController::class, 'store']);

    // Stock
    Route::get('/stock', [StockController::class, 'index']);

    // Reports
    Route::get('/reports/today', [ReportController::class, 'today']);
    Route::get('/reports/pnl', [ReportController::class, 'pnl']);
    Route::get('/reports/stock-alerts', [ReportController::class, 'stockAlerts']);
    Route::get('/reports/margin-alerts', [ReportController::class, 'marginAlerts']);
    Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
    Route::get('/reports/bottom-products', [ReportController::class, 'bottomProducts']);
    Route::get('/reports/aging', [ReportController::class, 'aging']);

    // Partners (Mitra)
    Route::get('/partners', [PartnerController::class, 'index']);
    Route::post('/partners', [PartnerController::class, 'store']);
    Route::get('/partners/{partner}', [PartnerController::class, 'show']);
    Route::put('/partners/{partner}', [PartnerController::class, 'update']);
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy']);
    Route::get('/partners/{partner}/aging', [PartnerController::class, 'aging']);

    // Invoices
    Route::get('/invoices/outstanding', [InvoiceController::class, 'outstanding']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);

    // Production Runs (Produksi)
    Route::get('/production-runs', [ProductionRunController::class, 'index']);
    Route::post('/production-runs', [ProductionRunController::class, 'store']);
    Route::get('/production-runs/{productionRun}', [ProductionRunController::class, 'show']);
    Route::delete('/production-runs/{productionRun}', [ProductionRunController::class, 'destroy']);
});
