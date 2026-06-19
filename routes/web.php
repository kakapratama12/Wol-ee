     1|<?php
     2|
     3|use App\Http\Controllers\AgingReportController;
     4|use App\Http\Controllers\InvoiceController;
     5|use App\Http\Controllers\PartnerController;
     6|use App\Http\Controllers\BotIntegrationController;
     7|use App\Http\Controllers\DashboardController;
     8|use App\Http\Controllers\ExpenseController;
     9|use App\Http\Controllers\IngredientController;
    10|use App\Http\Controllers\MarginController;
    11|use App\Http\Controllers\PnlController;
    12|use App\Http\Controllers\ProductController;
    13|use App\Http\Controllers\ProfileController;
    14|use App\Http\Controllers\Platform\PlatformController;
    15|use App\Http\Controllers\SaleController;
    16|use App\Http\Controllers\TaxController;
    17|use App\Http\Controllers\TransactionController;
    18|use Illuminate\Support\Facades\Route;
    19|
    20|Route::get('/', function () {
    21|    return redirect()->route('dashboard');
    22|});
    23|
    24|Route::middleware(['auth', 'verified'])->group(function () {
    25|    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    26|
    27|    // Inventory & transaksi (Owner + Admin)
    28|    Route::get('/inventory', [IngredientController::class, 'index'])->name('ingredients.index');
    29|    Route::post('/inventory', [IngredientController::class, 'store'])->name('ingredients.store')->middleware('owner');
        Route::post('/inventory/json', [IngredientController::class, 'storeJson'])->name('ingredients.storeJson')->middleware('owner');
    30|    Route::put('/inventory/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update')->middleware('owner');
    31|    Route::post('/inventory/{ingredient}/adjust', [IngredientController::class, 'adjust'])->name('ingredients.adjust');
    32|    Route::delete('/inventory/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy')->middleware('owner');
    33|
    34|    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    35|    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    36|    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    37|    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    38|
    39|    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    40|    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    41|    Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    42|    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    43|
    44|    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    45|    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
    46|
    47|    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    48|    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    49|
    50|    // Owner only
    51|    Route::middleware('owner')->group(function () {
    52|        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    53|        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::post('/products/json', [ProductController::class, 'storeJson'])->name('products.storeJson');
    54|        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    55|        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    56|        Route::put('/products/{product}/recipe', [ProductController::class, 'updateRecipe'])->name('products.recipe.update');
    57|
    58|        Route::get('/tax', [TaxController::class, 'index'])->name('tax.index');
    59|        Route::post('/tax', [TaxController::class, 'simulate'])->name('tax.simulate');
    60|
    61|        Route::get('/pnl', [PnlController::class, 'index'])->name('pnl.index');
    62|        Route::get('/pnl/export', [PnlController::class, 'export'])->name('pnl.export');
    63|
    64|        Route::get('/reports/aging', [AgingReportController::class, 'index'])->name('reports.aging');
    65|
    66|        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    67|        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    68|        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    69|        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    70|
    71|        Route::get('/margin', [MarginController::class, 'index'])->name('margin.index');
    72|        Route::post('/margin/what-if', [MarginController::class, 'whatIf'])->name('margin.whatif');
    73|
    74|        Route::get('/settings/bot', [BotIntegrationController::class, 'index'])->name('settings.bot');
    75|        Route::post('/settings/bot/token', [BotIntegrationController::class, 'generate'])->name('settings.bot.generate');
    76|
    77|        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
    78|        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
    79|        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');
    80|
    81|        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    82|        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    83|    });
    84|});
    85|
    86|Route::middleware(['auth', 'verified', 'super_admin'])
    87|    ->prefix('platform')
    88|    ->name('platform.')
    89|    ->group(function () {
    90|        Route::get('/', [PlatformController::class, 'overview'])->name('overview');
    91|        Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
    92|        Route::get('/feedback', [PlatformController::class, 'feedback'])->name('feedback');
    93|        Route::put('/feedback/{feedback}', [PlatformController::class, 'updateFeedback'])->name('feedback.update');
    94|        Route::get('/ai-usage', [PlatformController::class, 'aiUsage'])->name('ai-usage');
    95|        Route::get('/bot-skills', [PlatformController::class, 'botSkills'])->name('bot-skills');
    96|    });
    97|
    98|Route::middleware('auth')->group(function () {
    99|    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
   100|    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
   101|    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
   102|});
   103|
   104|require __DIR__.'/auth.php';
   105|