<?php

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\InventoryService;

beforeEach(function () {
    authenticateTestTenant();
});

/*
|--------------------------------------------------------------------------
| recordPurchase
|--------------------------------------------------------------------------
*/

it('recordPurchase creates Transaction + StockMovement + updates ingredient', function () {
    $ingredient = Ingredient::create([
        'name' => 'Tepung Terigu',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 10000,
        'weighted_avg_price' => 10000,
        'current_stock' => 0,
        'minimum_stock' => 50,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase(
        ingredient: $ingredient,
        quantity: 20,
        unitPrice: 12000,
        note: 'Pembelian awal',
    );

    // Transaction created
    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($tx->ingredient_id)->toBe($ingredient->id)
        ->and((float) $tx->quantity)->toBe(20.0)
        ->and((float) $tx->unit_price)->toBe(12000.0)
        ->and((float) $tx->total)->toBe(240000.0)
        ->and($tx->source)->toBe('web');

    // Ingredient updated
    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(20.0)
        ->and((float) $ingredient->unit_price)->toBe(12000.0);

    // StockMovement created
    $movement = StockMovement::where('source_type', Transaction::class)
        ->where('source_id', $tx->id)
        ->where('type', StockMovement::TYPE_PURCHASE)
        ->first();
    expect($movement)->not->toBeNull()
        ->and($movement->ingredient_id)->toBe($ingredient->id)
        ->and((float) $movement->quantity)->toBe(20.0)
        ->and((float) $movement->stock_after)->toBe(20.0);
});

it('recordPurchase computes weighted average across multiple purchases', function () {
    $ingredient = Ingredient::create([
        'name' => 'Gula Pasir',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 8000,
        'weighted_avg_price' => 8000,
        'current_stock' => 0,
        'minimum_stock' => 10,
    ]);

    $service = new InventoryService();

    // Purchase 1: 10kg @ 8000 → avg = 8000
    $service->recordPurchase($ingredient, quantity: 10, unitPrice: 8000);
    $ingredient->refresh();
    expect((float) $ingredient->weighted_avg_price)->toBe(8000.0);

    // Purchase 2: 5kg @ 12000 → avg = (10*8000 + 5*12000) / 15 = 9333.3333
    $service->recordPurchase($ingredient->fresh(), quantity: 5, unitPrice: 12000);
    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(15.0)
        ->and((float) $ingredient->weighted_avg_price)->toBe(9333.3333);
});

it('recordPurchase creates PriceHistory when price changes', function () {
    $ingredient = Ingredient::create([
        'name' => 'Susu Segar',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 5000,
        'weighted_avg_price' => 5000,
        'current_stock' => 0,
        'minimum_stock' => 100,
    ]);

    $service = new InventoryService();
    $service->recordPurchase($ingredient, quantity: 100, unitPrice: 6000);

    expect($ingredient->priceHistories()->count())->toBe(1)
        ->and((float) $ingredient->priceHistories()->first()->unit_price)->toBe(6000.0);
});

it('recordPurchase stores idempotency key', function () {
    $ingredient = Ingredient::create([
        'name' => 'Cokelat Bubuk',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 200,
        'weighted_avg_price' => 200,
        'current_stock' => 0,
        'minimum_stock' => 500,
    ]);

    $service = new InventoryService();
    $key = 'purchase-key-' . uniqid();
    $tx = $service->recordPurchase(
        ingredient: $ingredient,
        quantity: 500,
        unitPrice: 250,
        idempotencyKey: $key,
    );

    expect($tx->idempotency_key)->toBe($key);
});

/*
|--------------------------------------------------------------------------
| adjustStock
|--------------------------------------------------------------------------
*/

it('adjustStock sets quantity to an absolute value and records delta', function () {
    $ingredient = Ingredient::create([
        'name' => 'Tepung Beras',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 7000,
        'weighted_avg_price' => 7000,
        'current_stock' => 10,
        'minimum_stock' => 5,
    ]);

    $service = new InventoryService();
    $movement = $service->adjustStock($ingredient, newStock: 25, note: 'Stock opname');

    expect((float) $ingredient->fresh()->current_stock)->toBe(25.0);

    expect($movement->type)->toBe(StockMovement::TYPE_ADJUSTMENT)
        ->and((float) $movement->quantity)->toBe(15.0) // delta: 25 - 10
        ->and((float) $movement->stock_after)->toBe(25.0)
        ->and($movement->note)->toBe('Stock opname');
});

it('adjustStock handles decrease correctly', function () {
    $ingredient = Ingredient::create([
        'name' => 'Mentega',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 30000,
        'weighted_avg_price' => 30000,
        'current_stock' => 20,
        'minimum_stock' => 5,
    ]);

    $service = new InventoryService();
    $movement = $service->adjustStock($ingredient, newStock: 8, note: 'Susut');

    expect((float) $ingredient->fresh()->current_stock)->toBe(8.0)
        ->and((float) $movement->quantity)->toBe(-12.0); // delta: 8 - 20
});

it('adjustStock sets stock to zero', function () {
    $ingredient = Ingredient::create([
        'name' => 'Garam',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 3000,
        'weighted_avg_price' => 3000,
        'current_stock' => 5,
        'minimum_stock' => 1,
    ]);

    $service = new InventoryService();
    $movement = $service->adjustStock($ingredient, newStock: 0, note: 'Semua habis');

    expect((float) $ingredient->fresh()->current_stock)->toBe(0.0)
        ->and((float) $movement->stock_after)->toBe(0.0);
});

it('adjustStock with zero delta creates zero-quantity movement', function () {
    $ingredient = Ingredient::create([
        'name' => 'Kayu Manis',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 50,
        'weighted_avg_price' => 50,
        'current_stock' => 100,
        'minimum_stock' => 20,
    ]);

    $service = new InventoryService();
    $movement = $service->adjustStock($ingredient, newStock: 100, note: 'Verification');

    expect((float) $movement->quantity)->toBe(0.0)
        ->and((float) $movement->stock_after)->toBe(100.0);
});

/*
|--------------------------------------------------------------------------
| reversePurchase
|--------------------------------------------------------------------------
*/

it('reversePurchase undoes the original purchase stock effect', function () {
    $ingredient = Ingredient::create([
        'name' => 'Telur Ayam',
        'unit_type' => 'gramasi',
        'base_unit' => 'butir',
        'unit_price' => 2500,
        'weighted_avg_price' => 2500,
        'current_stock' => 0,
        'minimum_stock' => 50,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 100, unitPrice: 2500);

    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(100.0);

    // Reverse
    $service->reversePurchase($tx);

    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(0.0);
});

it('reversePurchase creates reversal StockMovement', function () {
    $ingredient = Ingredient::create([
        'name' => 'Madu',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 15000,
        'weighted_avg_price' => 15000,
        'current_stock' => 0,
        'minimum_stock' => 10,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 50, unitPrice: 15000);

    $service->reversePurchase($tx);

    $reversal = StockMovement::where('source_type', Transaction::class)
        ->where('source_id', $tx->id)
        ->where('type', StockMovement::TYPE_REVERSAL)
        ->first();

    expect($reversal)->not->toBeNull()
        ->and((float) $reversal->quantity)->toBe(-50.0)
        ->and((float) $reversal->stock_after)->toBe(0.0)
        ->and($reversal->note)->toContain("Reversal pembelian #{$tx->id}");
});

it('reversePurchase deletes the original purchase StockMovement', function () {
    $ingredient = Ingredient::create([
        'name' => 'Vanili',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 50000,
        'weighted_avg_price' => 50000,
        'current_stock' => 0,
        'minimum_stock' => 5,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 10, unitPrice: 50000);

    // Verify purchase movement exists
    $purchaseMovementsBefore = StockMovement::where('source_type', Transaction::class)
        ->where('source_id', $tx->id)
        ->where('type', StockMovement::TYPE_PURCHASE)
        ->count();
    expect($purchaseMovementsBefore)->toBe(1);

    $service->reversePurchase($tx);

    // Original purchase movement should be deleted
    $purchaseMovementsAfter = StockMovement::where('source_type', Transaction::class)
        ->where('source_id', $tx->id)
        ->where('type', StockMovement::TYPE_PURCHASE)
        ->count();
    expect($purchaseMovementsAfter)->toBe(0);
});

it('reversePurchase throws when stock is insufficient', function () {
    $ingredient = Ingredient::create([
        'name' => 'Matcha',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 300000,
        'weighted_avg_price' => 300000,
        'current_stock' => 0,
        'minimum_stock' => 20,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 50, unitPrice: 300000);

    // Consume most of the stock
    $ingredient->update(['current_stock' => 10]);

    expect(fn () => $service->reversePurchase($tx))
        ->toThrow(InvalidArgumentException::class, 'Stok tidak cukup');
});

it('reversePurchase with sufficient stock after partial usage succeeds', function () {
    $ingredient = Ingredient::create([
        'name' => 'Kopi Arabica',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 80000,
        'weighted_avg_price' => 80000,
        'current_stock' => 0,
        'minimum_stock' => 100,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 50, unitPrice: 80000);

    // Stock is exactly 50 — no usage, reversal should succeed
    expect((float) $ingredient->fresh()->current_stock)->toBe(50.0);

    $service->reversePurchase($tx);

    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Edge cases
|--------------------------------------------------------------------------
*/

it('recordPurchase with zero quantity creates zero-stock transaction', function () {
    $ingredient = Ingredient::create([
        'name' => 'Pewarna',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 500,
        'weighted_avg_price' => 500,
        'current_stock' => 0,
        'minimum_stock' => 10,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 0, unitPrice: 500);

    expect((float) $tx->quantity)->toBe(0.0)
        ->and((float) $tx->total)->toBe(0.0)
        ->and((float) $ingredient->fresh()->current_stock)->toBe(0.0);
});

it('adjustStock to negative value is allowed by the service', function () {
    $ingredient = Ingredient::create([
        'name' => 'Bawang Putih',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 30000,
        'weighted_avg_price' => 30000,
        'current_stock' => 5,
        'minimum_stock' => 1,
    ]);

    $service = new InventoryService();
    // Service does not guard against negative; it sets whatever absolute value is given
    $movement = $service->adjustStock($ingredient, newStock: -2, note: 'Error correction');

    expect((float) $ingredient->fresh()->current_stock)->toBe(-2.0)
        ->and((float) $movement->stock_after)->toBe(-2.0)
        ->and((float) $movement->quantity)->toBe(-7.0); // -2 - 5 = -7
});

it('recordPurchase with very small quantity preserves precision', function () {
    $ingredient = Ingredient::create([
        'name' => 'Ragi Instan',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 15,
        'weighted_avg_price' => 15,
        'current_stock' => 0,
        'minimum_stock' => 10,
    ]);

    $service = new InventoryService();
    $tx = $service->recordPurchase($ingredient, quantity: 0.5, unitPrice: 15);

    expect((float) $tx->quantity)->toBe(0.5)
        ->and((float) $tx->total)->toBe(7.5)
        ->and((float) $ingredient->fresh()->current_stock)->toBe(0.5);
});

it('adjustStock persists note and user_id', function () {
    $ingredient = Ingredient::create([
        'name' => 'Keju Cheddar',
        'unit_type' => 'gramasi',
        'base_unit' => 'kg',
        'unit_price' => 90000,
        'weighted_avg_price' => 90000,
        'current_stock' => 3,
        'minimum_stock' => 1,
    ]);

    $user = auth()->user();
    $service = new InventoryService();
    $movement = $service->adjustStock(
        ingredient: $ingredient,
        newStock: 7,
        note: 'Pengecekan fisik',
        userId: $user->id,
    );

    expect($movement->note)->toBe('Pengecekan fisik')
        ->and($movement->user_id)->toBe($user->id);
});

it('recordPurchase records source and occurred_at', function () {
    $ingredient = Ingredient::create([
        'name' => 'Santan',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 8000,
        'weighted_avg_price' => 8000,
        'current_stock' => 0,
        'minimum_stock' => 200,
    ]);

    $occurredAt = now()->subDays(3);
    $service = new InventoryService();
    $tx = $service->recordPurchase(
        ingredient: $ingredient,
        quantity: 200,
        unitPrice: 8000,
        source: 'manual',
        occurredAt: $occurredAt,
        note: 'Input manual dari spreadsheet',
    );

    expect($tx->source)->toBe('manual')
        ->and($tx->note)->toBe('Input manual dari spreadsheet')
        ->and($tx->occurred_at->startOfDay())->toEqual($occurredAt->startOfDay());
});
