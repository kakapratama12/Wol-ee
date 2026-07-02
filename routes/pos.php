<?php

use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\PosAuthController;
use App\Http\Controllers\Pos\RegisterController;
use App\Http\Controllers\Pos\SessionController;
use Illuminate\Support\Facades\Route;

// Login sudah di /login (satu halaman untuk semua role)
// Redirect /pos/login ke /login
Route::get('/login', fn () => redirect()->route('login'))->name('login');

Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('/logout', [PosAuthController::class, 'destroy'])->name('logout');
    Route::get('/', [SessionController::class, 'landing'])->name('landing');
    Route::get('/entry', [SessionController::class, 'entry'])->name('entry');

    Route::get('/session/open', [SessionController::class, 'openForm'])->name('session.open.form');
    Route::post('/session/open', [SessionController::class, 'open'])->name('session.open');
    Route::get('/session/summary', [SessionController::class, 'summaryPage'])->name('session.summary.page');
    Route::post('/session/summary/skip', [SessionController::class, 'skipSummary'])->name('session.summary.skip');
    Route::get('/session/close', [SessionController::class, 'closeForm'])->name('session.close.form');
    Route::post('/session/close', [SessionController::class, 'close'])->name('session.close');

    Route::get('/session/status', [SessionController::class, 'show'])->name('session.show');
    Route::get('/session/summary/json', [SessionController::class, 'summary'])->name('session.summary');

    Route::middleware('pos.session')->group(function () {
        Route::get('/register', [RegisterController::class, 'index'])->name('register');
        Route::get('/orders/{order}/success', [RegisterController::class, 'success'])->name('orders.success');
        Route::get('/orders/{order}/receipt', [RegisterController::class, 'receipt'])->name('orders.receipt');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/void', [OrderController::class, 'void'])->name('orders.void');
    });
});

// Stock management
Route::middleware(['auth', 'staff'])->prefix('stock')->name('stock.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Pos\StockController::class, 'index'])->name('index');
    Route::get('/purchase', [\App\Http\Controllers\Pos\StockController::class, 'purchaseForm'])->name('purchase');
    Route::get('/adjust', [\App\Http\Controllers\Pos\StockController::class, 'adjustForm'])->name('adjust');
    Route::get('/movements', [\App\Http\Controllers\Pos\StockController::class, 'movements'])->name('movements');
});
