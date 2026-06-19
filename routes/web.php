<?php

use App\Http\Controllers\AgingReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\BotIntegrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MarginController;
use App\Http\Controllers\PnlController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\FinishedGoodsController;
use App\Http\Controllers\ProductionRunController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Inventory & transaksi (Owner + Admin)
    Route::get('/inventory', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::post('/inventory', [IngredientController::class, 'store'])->name('ingredients.store')->middleware('owner');
        Route::post('/inventory/json', [IngredientController::class, 'storeJson'])->name('ingredients.storeJson')->middleware('owner');
    Route::put('/inventory/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update')->middleware('owner');
    Route::post('/inventory/{ingredient}/adjust', [IngredientController::class, 'adjust'])->name('ingredients.adjust');
    Route::delete('/inventory/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy')->middleware('owner');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

        Route::get("/production-runs", [ProductionRunController::class, "index"])->name("production-runs.index");
        Route::post("/production-runs", [ProductionRunController::class, "store"])->name("production-runs.store");
        Route::put("/production-runs/{productionRun}/yield", [ProductionRunController::class, "updateYield"])->name("production-runs.updateYield");
        Route::delete("/production-runs/{productionRun}", [ProductionRunController::class, "destroy"])->name("production-runs.destroy");

        Route::get("/finished-goods", [FinishedGoodsController::class, "index"])->name("finished-goods.index");
        Route::post("/finished-goods/{product}/adjust", [FinishedGoodsController::class, "adjustStock"])->name("finished-goods.adjust");

    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    // Owner only
    Route::middleware('owner')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::post('/products/json', [ProductController::class, 'storeJson'])->name('products.storeJson');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::put('/products/{product}/recipe', [ProductController::class, 'updateRecipe'])->name('products.recipe.update');

        Route::get('/tax', [TaxController::class, 'index'])->name('tax.index');
        Route::post('/tax', [TaxController::class, 'simulate'])->name('tax.simulate');

        Route::get('/pnl', [PnlController::class, 'index'])->name('pnl.index');
        Route::get('/pnl/export', [PnlController::class, 'export'])->name('pnl.export');

        Route::get('/reports/aging', [AgingReportController::class, 'index'])->name('reports.aging');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('/margin', [MarginController::class, 'index'])->name('margin.index');
        Route::post('/margin/what-if', [MarginController::class, 'whatIf'])->name('margin.whatif');

        Route::get('/settings/bot', [BotIntegrationController::class, 'index'])->name('settings.bot');
        Route::post('/settings/bot/token', [BotIntegrationController::class, 'generate'])->name('settings.bot.generate');

        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');

        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    });
});

Route::middleware(['auth', 'verified', 'super_admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('/', [PlatformController::class, 'overview'])->name('overview');
        Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
        Route::get('/feedback', [PlatformController::class, 'feedback'])->name('feedback');
        Route::put('/feedback/{feedback}', [PlatformController::class, 'updateFeedback'])->name('feedback.update');
        Route::get('/ai-usage', [PlatformController::class, 'aiUsage'])->name('ai-usage');
        Route::get('/bot-skills', [PlatformController::class, 'botSkills'])->name('bot-skills');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
