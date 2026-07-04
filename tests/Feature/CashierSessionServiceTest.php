<?php

use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CashierSessionService;
use App\Services\ProductAvailabilityService;

/*
 * Helper: create a CashierSessionService with mocked ProductAvailabilityService
 * so we don't need real ingredients/products for the opening snapshot.
 */
function cashierService(): CashierSessionService
{
    $mock = mock(ProductAvailabilityService::class);
    $mock->shouldReceive('buildOpeningSummary')->andReturn([]);

    return new CashierSessionService($mock);
}

/*
 * Helper: create a user with an assigned outlet for POS tests.
 */
function setupCashierUser(): \App\Models\User
{
    $user = authenticateTestTenant();
    $outlet = Outlet::create([
        'tenant_id' => $user->tenant_id,
        'name' => 'Outlet Utama',
        'is_active' => true,
    ]);
    $user->update(['outlet_id' => $outlet->id]);

    return $user;
}

// ---------------------------------------------------------------------------
// 1) open() creates a new session
// ---------------------------------------------------------------------------
it('open() creates a new cashier session with correct attributes', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 500000);

    expect($session)->toBeInstanceOf(CashierSession::class)
        ->and($session->user_id)->toBe($user->id)
        ->and($session->tenant_id)->toBe($user->tenant_id)
        ->and($session->outlet_id)->toBe($user->outlet_id)
        ->and((float) $session->opening_cash)->toBe(500000.0)
        ->and($session->opened_at)->not->toBeNull()
        ->and($session->closed_at)->toBeNull();

    $this->assertDatabaseHas('cashier_sessions', [
        'user_id' => $user->id,
        'opening_cash' => 500000.00,
    ]);
});

// ---------------------------------------------------------------------------
// 2) findOpenSession() returns the active session
// ---------------------------------------------------------------------------
it('findOpenSession() returns the open session for a user', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $opened = $service->open($user, openingCash: 200000);

    $found = $service->findOpenSession($user);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($opened->id);
});

it('findOpenSession() returns null when no open session exists', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $found = $service->findOpenSession($user);

    expect($found)->toBeNull();
});

it('findOpenSession() does not return a closed session', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);
    $service->close($session, actualCash: 100000);

    $found = $service->findOpenSession($user);

    expect($found)->toBeNull();
});

// ---------------------------------------------------------------------------
// 3) close() calculates variance correctly
// ---------------------------------------------------------------------------
it('close() calculates positive variance when actual > expected', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);
    // Simulate some cash sales: total_cash is an attribute on the session
    $session->update(['total_cash' => 500000]);
    $session->refresh();

    // expected = 100000 + 500000 = 600000
    // actual = 610000 → variance = +10000
    $summary = $service->close($session, actualCash: 610000, closingNote: 'Lebih sedikit');

    expect($summary['opening_cash'])->toBe(100000.0)
        ->and($summary['total_cash'])->toBe(500000.0)
        ->and($summary['expected_cash'])->toBe(600000.0)
        ->and($summary['actual_cash'])->toBe(610000.0)
        ->and($summary['variance'])->toBe(10000.0);

    $session->refresh();
    expect($session->closed_at)->not->toBeNull()
        ->and($session->closing_note)->toBe('Lebih sedikit')
        ->and((float) $session->variance)->toBe(10000.0);
});

it('close() calculates negative variance when actual < expected', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 200000);
    $session->update(['total_cash' => 300000]);
    $session->refresh();

    // expected = 200000 + 300000 = 500000
    // actual = 495000 → variance = -5000
    $summary = $service->close($session, actualCash: 495000);

    expect($summary['variance'])->toBe(-5000.0)
        ->and($summary['expected_cash'])->toBe(500000.0);

    $session->refresh();
    expect((float) $session->variance)->toBe(-5000.0);
});

it('close() returns zero variance when actual equals expected', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);
    $session->update(['total_cash' => 400000]);
    $session->refresh();

    // expected = 100000 + 400000 = 500000, actual = 500000
    $summary = $service->close($session, actualCash: 500000);

    expect($summary['variance'])->toBe(0.0);
});

it('close() throws when session is already closed', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);
    $service->close($session, actualCash: 100000);

    // Second close should throw
    $service->close($session, actualCash: 100000);
})->throws(\InvalidArgumentException::class, 'Sesi kasir sudah ditutup.');

// ---------------------------------------------------------------------------
// 4) Double-open prevention
// ---------------------------------------------------------------------------
it('open() throws when user already has an open session', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $service->open($user, openingCash: 100000);

    // Second open without closing first → exception
    $service->open($user, openingCash: 200000);
})->throws(\InvalidArgumentException::class, 'Masih ada sesi kasir yang belum ditutup.');

it('open() allows a new session after previous one is closed', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $first = $service->open($user, openingCash: 100000);
    $service->close($first, actualCash: 100000);

    $second = $service->open($user, openingCash: 200000);

    expect($second->id)->not->toBe($first->id)
        ->and((float) $second->opening_cash)->toBe(200000.0);
});

// ---------------------------------------------------------------------------
// 5) salesSummary() returns correct per-product totals
//    (The closest equivalent to "getTodaySummary" — aggregates sales within
//     a session grouped by product name.)
// ---------------------------------------------------------------------------
it('salesSummary() returns empty array when session has no completed orders', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);

    $result = $service->salesSummary($session);

    expect($result)->toBeEmpty();
});

it('salesSummary() aggregates sales grouped by product', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);

    $productA = Product::create(['name' => 'Matcha Latte', 'selling_price' => 45000]);
    $productB = Product::create(['name' => 'Croissant', 'selling_price' => 25000]);

    // Order 1: 2× Matcha Latte (revenue 90000)
    $order1 = PosOrder::create([
        'tenant_id' => $user->tenant_id,
        'cashier_session_id' => $session->id,
        'outlet_id' => $user->outlet_id,
        'user_id' => $user->id,
        'total' => 90000,
        'payment_method' => 'tunai',
        'amount_paid' => 100000,
        'change_amount' => 10000,
        'status' => PosOrder::STATUS_COMPLETED,
    ]);

    Sale::create([
        'idempotency_key' => uniqid(),
        'user_id' => $user->id,
        'product_id' => $productA->id,
        'pos_order_id' => $order1->id,
        'outlet_id' => $user->outlet_id,
        'quantity' => 2,
        'unit_price' => 45000,
        'revenue' => 90000,
        'cogs' => 10000,
        'profit' => 80000,
        'margin' => 88.89,
        'source' => Sale::SOURCE_POS,
        'status' => Sale::STATUS_ACTIVE,
        'occurred_at' => now(),
    ]);

    // Order 2: 1× Croissant (revenue 25000)
    $order2 = PosOrder::create([
        'tenant_id' => $user->tenant_id,
        'cashier_session_id' => $session->id,
        'outlet_id' => $user->outlet_id,
        'user_id' => $user->id,
        'total' => 25000,
        'payment_method' => 'tunai',
        'amount_paid' => 30000,
        'change_amount' => 5000,
        'status' => PosOrder::STATUS_COMPLETED,
    ]);

    Sale::create([
        'idempotency_key' => uniqid(),
        'user_id' => $user->id,
        'product_id' => $productB->id,
        'pos_order_id' => $order2->id,
        'outlet_id' => $user->outlet_id,
        'quantity' => 1,
        'unit_price' => 25000,
        'revenue' => 25000,
        'cogs' => 5000,
        'profit' => 20000,
        'margin' => 80.0,
        'source' => Sale::SOURCE_POS,
        'status' => Sale::STATUS_ACTIVE,
        'occurred_at' => now(),
    ]);

    // Order 3: 1× Matcha Latte again (revenue 45000) — should aggregate with Order 1
    $order3 = PosOrder::create([
        'tenant_id' => $user->tenant_id,
        'cashier_session_id' => $session->id,
        'outlet_id' => $user->outlet_id,
        'user_id' => $user->id,
        'total' => 45000,
        'payment_method' => 'qris',
        'amount_paid' => 45000,
        'change_amount' => 0,
        'status' => PosOrder::STATUS_COMPLETED,
    ]);

    Sale::create([
        'idempotency_key' => uniqid(),
        'user_id' => $user->id,
        'product_id' => $productA->id,
        'pos_order_id' => $order3->id,
        'outlet_id' => $user->outlet_id,
        'quantity' => 1,
        'unit_price' => 45000,
        'revenue' => 45000,
        'cogs' => 5000,
        'profit' => 40000,
        'margin' => 88.89,
        'source' => Sale::SOURCE_POS,
        'status' => Sale::STATUS_ACTIVE,
        'occurred_at' => now(),
    ]);

    $summary = $service->salesSummary($session);

    expect($summary)->toHaveCount(2);

    // Should be sorted by revenue descending (Matcha: 135000, Croissant: 25000)
    $matcha = collect($summary)->firstWhere('product', 'Matcha Latte');
    $croissant = collect($summary)->firstWhere('product', 'Croissant');

    expect($matcha)->not->toBeNull()
        ->and($matcha['quantity'])->toBe(3)
        ->and($matcha['revenue'])->toBe(135000.0);

    expect($croissant)->not->toBeNull()
        ->and($croissant['quantity'])->toBe(1)
        ->and($croissant['revenue'])->toBe(25000.0);
});

it('salesSummary() excludes void orders', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);

    $product = Product::create(['name' => 'Kopi Susu', 'selling_price' => 30000]);

    // Completed order
    $order1 = PosOrder::create([
        'tenant_id' => $user->tenant_id,
        'cashier_session_id' => $session->id,
        'outlet_id' => $user->outlet_id,
        'user_id' => $user->id,
        'total' => 30000,
        'payment_method' => 'tunai',
        'amount_paid' => 30000,
        'status' => PosOrder::STATUS_COMPLETED,
    ]);

    Sale::create([
        'idempotency_key' => uniqid(),
        'user_id' => $user->id,
        'product_id' => $product->id,
        'pos_order_id' => $order1->id,
        'outlet_id' => $user->outlet_id,
        'quantity' => 1,
        'unit_price' => 30000,
        'revenue' => 30000,
        'cogs' => 8000,
        'profit' => 22000,
        'margin' => 73.33,
        'source' => Sale::SOURCE_POS,
        'status' => Sale::STATUS_ACTIVE,
        'occurred_at' => now(),
    ]);

    // Void order — should NOT appear in summary
    $order2 = PosOrder::create([
        'tenant_id' => $user->tenant_id,
        'cashier_session_id' => $session->id,
        'outlet_id' => $user->outlet_id,
        'user_id' => $user->id,
        'total' => 30000,
        'payment_method' => 'tunai',
        'amount_paid' => 30000,
        'status' => PosOrder::STATUS_VOID,
    ]);

    Sale::create([
        'idempotency_key' => uniqid(),
        'user_id' => $user->id,
        'product_id' => $product->id,
        'pos_order_id' => $order2->id,
        'outlet_id' => $user->outlet_id,
        'quantity' => 1,
        'unit_price' => 30000,
        'revenue' => 30000,
        'cogs' => 8000,
        'profit' => 22000,
        'margin' => 73.33,
        'source' => Sale::SOURCE_POS,
        'status' => Sale::STATUS_VOID,
        'occurred_at' => now(),
    ]);

    $summary = $service->salesSummary($session);

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['quantity'])->toBe(1)
        ->and($summary[0]['revenue'])->toBe(30000.0);
});

// ---------------------------------------------------------------------------
// Close summary includes totals from all payment methods
// ---------------------------------------------------------------------------
it('close() summary includes qris and transfer totals', function () {
    $user = setupCashierUser();
    $service = cashierService();

    $session = $service->open($user, openingCash: 100000);
    $session->update([
        'total_cash' => 200000,
        'total_qris' => 150000,
        'total_transfer' => 100000,
    ]);
    $session->refresh();

    $summary = $service->close($session, actualCash: 300000);

    expect($summary['total_cash'])->toBe(200000.0)
        ->and($summary['total_qris'])->toBe(150000.0)
        ->and($summary['total_transfer'])->toBe(100000.0)
        ->and($summary['total_omset'])->toBe(450000.0)
        ->and($summary['expected_cash'])->toBe(300000.0); // 100000 + 200000
});
