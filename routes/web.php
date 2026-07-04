<?php

use App\Http\Controllers\AgingReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\BotIntegrationController;
use App\Http\Controllers\CashEntryController;
use App\Http\Controllers\CashflowController;
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
use App\Http\Controllers\PrepStockController;
use App\Http\Controllers\ProductionRunController;
use App\Http\Controllers\CompanySettingsController;
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

    Route::middleware('owner')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    });

    Route::middleware('owner')->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    });

        Route::get("/production-runs", [ProductionRunController::class, "index"])->name("production-runs.index");
        Route::post("/production-runs", [ProductionRunController::class, "store"])->name("production-runs.store");
        Route::put("/production-runs/{productionRun}/yield", [ProductionRunController::class, "updateYield"])->name("production-runs.updateYield");
        Route::put("/production-runs/{productionRun}/items", [ProductionRunController::class, "updateItems"])->name("production-runs.updateItems");
        Route::delete("/production-runs/{productionRun}", [ProductionRunController::class, "destroy"])->name("production-runs.destroy");

        Route::get("/finished-goods", [FinishedGoodsController::class, "index"])->name("finished-goods.index");
        Route::post("/finished-goods/{product}/adjust", [FinishedGoodsController::class, "adjustStock"])->name("finished-goods.adjust");

        Route::get("/prep-stocks", [PrepStockController::class, "index"])->name("prep-stocks.index");
        Route::post("/prep-stocks/{ingredient}/adjust", [PrepStockController::class, "adjustStock"])->name("prep-stocks.adjust");

    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/pdf/preview', [InvoiceController::class, 'pdfPreview'])->name('invoices.pdf-preview');
    Route::get('/invoices/{invoice}/kuitansi', [InvoiceController::class, 'kuitansi'])->name('invoices.kuitansi');

    // Multi-outlet
    Route::get('/outlets', [\App\Http\Controllers\OutletController::class, 'index'])->name('outlets.index');
    Route::get('/outlets/{outlet}', [\App\Http\Controllers\OutletController::class, 'show'])->name('outlets.show');
    Route::get('/outlets/{outlet}/inventory', [\App\Http\Controllers\OutletInventoryController::class, 'index'])->name('outlets.inventory');
    Route::get('/distributions', [\App\Http\Controllers\DistributionController::class, 'index'])->name('distributions.index');
    Route::get('/distributions/{distribution}', [\App\Http\Controllers\DistributionController::class, 'show'])->name('distributions.show');
    Route::middleware('owner')->group(function () {
        Route::post('/outlets', [\App\Http\Controllers\OutletController::class, 'store'])->name('outlets.store');
        Route::put('/outlets/{outlet}', [\App\Http\Controllers\OutletController::class, 'update'])->name('outlets.update');
        Route::delete('/outlets/{outlet}', [\App\Http\Controllers\OutletController::class, 'destroy'])->name('outlets.destroy');
        Route::post('/distributions', [\App\Http\Controllers\DistributionController::class, 'store'])->name('distributions.store');
        Route::get('/distributions/{distribution}/edit', [\App\Http\Controllers\DistributionController::class, 'edit'])->name('distributions.edit');
        Route::put('/distributions/{distribution}', [\App\Http\Controllers\DistributionController::class, 'update'])->name('distributions.update');
        Route::delete('/distributions/{distribution}', [\App\Http\Controllers\DistributionController::class, 'destroy'])->name('distributions.destroy');
        Route::post('/outlets/{outlet}/inventory/adjust', [\App\Http\Controllers\OutletInventoryController::class, 'adjust'])->name('outlets.inventory.adjust');
        Route::post('/outlets/{outlet}/stock/purchase', [\App\Http\Controllers\OutletStockController::class, 'purchase'])->name('outlets.stock.purchase');
        Route::post('/outlets/{outlet}/stock/adjust', [\App\Http\Controllers\OutletStockController::class, 'adjust'])->name('outlets.stock.adjust');
    });
    Route::get('/outlets/{outlet}/stock/movements', [\App\Http\Controllers\OutletStockController::class, 'movements'])->name('outlets.stock.movements');
    Route::get('/outlets/{outlet}/inventory/movements', [\App\Http\Controllers\OutletInventoryController::class, 'movements'])->name('outlets.stock.movements.page');

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

        Route::get('/reports/cashflow', [CashflowController::class, 'index'])->name('reports.cashflow');
        Route::post('/cash-entries', [CashEntryController::class, 'store'])->name('cash-entries.store');
        Route::delete('/cash-entries/{cashEntry}', [CashEntryController::class, 'destroy'])->name('cash-entries.destroy');

        Route::get('/reports/aging', [AgingReportController::class, 'index'])->name('reports.aging');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('/margin', [MarginController::class, 'index'])->name('margin.index');
        Route::post('/margin/what-if', [MarginController::class, 'whatIf'])->name('margin.whatif');

        Route::get('/settings/bot', [BotIntegrationController::class, 'index'])->name('settings.bot');
        Route::post('/settings/bot/token', [BotIntegrationController::class, 'generate'])->name('settings.bot.generate');

        Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
        Route::put('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::post('/partners/json', [PartnerController::class, 'storeJson'])->name('partners.store-json');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');

        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('/invoices/{invoice}/archive', [InvoiceController::class, 'archive'])->name('invoices.archive');
    });

    // AP — Tagihan Supplier
    Route::middleware('owner')->group(function () {
        Route::get('/payables', [PayableController::class, 'index'])->name('payables.index');
        Route::get('/payables/{payable}', [PayableController::class, 'show'])->name('payables.show');
        Route::post('/payables', [PayableController::class, 'store'])->name('payables.store');
        Route::post('/payables/{payable}/pay', [PayableController::class, 'pay'])->name('payables.pay');
        Route::post('/payables/{payable}/archive', [PayableController::class, 'archive'])->name('payables.archive');
    });

    // Staff management (pengelola only)
    Route::middleware('owner')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::put('/staff/{staff}/password', [StaffController::class, 'resetPassword'])->name('staff.password');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
});

Route::middleware(['auth', 'verified', 'super_admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('/', [PlatformController::class, 'overview'])->name('overview');
        Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
        Route::post('/tenants', [PlatformController::class, 'storeTenant'])->name('tenants.store');
        Route::put('/tenants/{tenant}', [PlatformController::class, 'updateTenant'])->name('tenants.update');
        Route::get('/feedback', [PlatformController::class, 'feedback'])->name('feedback');
        Route::put('/feedback/{feedback}', [PlatformController::class, 'updateFeedback'])->name('feedback.update');
        Route::get('/ai-usage', [PlatformController::class, 'aiUsage'])->name('ai-usage');
        Route::get('/bot-skills', [PlatformController::class, 'botSkills'])->name('bot-skills');
        Route::get("/users", [PlatformController::class, "users"])->name("users");
        Route::post("/users", [PlatformController::class, "storeUser"])->name("users.store");
        Route::put("/users/{user}", [PlatformController::class, "updateUser"])->name("users.update");
        Route::put("/users/{user}/password", [PlatformController::class, "resetPassword"])->name("users.password");
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';