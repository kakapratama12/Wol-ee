<?php

use App\Models\CashierSession;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Services\PosOrderService;

beforeEach(function () {
    $this->user = authenticateTestTenant();
    $this->tenant = $this->user->tenant;

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Outlet Utama',
        'is_active' => true,
    ]);

    $this->session = CashierSession::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'user_id' => $this->user->id,
        'opening_cash' => 100000,
        'total_cash' => 0,
        'total_qris' => 0,
        'total_transfer' => 0,
        'opened_at' => now(),
    ]);
});

/**
 * Create a unit-type product with recipe and seed outlet_inventory.
 */
function createUnitProductWithOutletStock(Outlet $outlet, $tenant, float $stock = 5000): array
{
    $ingredient = Ingredient::create([
        'tenant_id' => $tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 20,
        'current_stock' => $stock,
        'minimum_stock' => 100,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'name' => 'Matcha Latte',
        'unit' => 'pcs',
        'selling_price' => 25000,
        'recipe_type' => Product::RECIPE_UNIT,
        'is_active' => true,
    ]);

    RecipeItem::create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 200, // 200ml per unit
    ]);

    OutletInventory::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'ingredient_id' => $ingredient->id,
        'product_id' => null,
        'quantity' => $stock,
        'unit' => 'ml',
    ]);

    return compact('ingredient', 'product');
}

function posService(): PosOrderService
{
    return app(PosOrderService::class);
}

/*
|--------------------------------------------------------------------------
| 1) recordSale creates sale + deducts outlet stock
|--------------------------------------------------------------------------
*/

it('creates PosOrder + Sale + deducts outlet stock on checkout', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithOutletStock(
        $this->outlet, $this->tenant, 5000
    );

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 2]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 100000,
    );

    // PosOrder created correctly
    expect($order)->toBeInstanceOf(PosOrder::class)
        ->and((float) $order->total)->toBe(50000.0)   // 25000 × 2
        ->and($order->status)->toBe(PosOrder::STATUS_COMPLETED)
        ->and($order->payment_method)->toBe(PosOrder::PAYMENT_TUNAI)
        ->and((float) $order->amount_paid)->toBe(100000.0)
        ->and((float) $order->change_amount)->toBe(50000.0); // 100000 − 50000

    // Sale recorded with POS source
    $this->assertDatabaseHas('sales', [
        'product_id' => $product->id,
        'source' => Sale::SOURCE_POS,
        'outlet_id' => $this->outlet->id,
        'pos_order_id' => $order->id,
    ]);

    // Outlet inventory deducted: 5000 − (200 × 2) = 4600
    $inventory = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();
    expect($inventory)->not->toBeNull()
        ->and((float) $inventory->quantity)->toBe(4600.0);
});

it('increments session totals for cash payment', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 25000,
    );

    $this->session->refresh();
    expect((float) $this->session->total_cash)->toBe(25000.0);
});

it('increments session totals for QRIS payment', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_QRIS,
        amountPaid: 25000,
    );

    $this->session->refresh();
    expect((float) $this->session->total_qris)->toBe(25000.0);
});

it('records multi-product checkout as separate sales', function () {
    $ingredient = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 20,
        'current_stock' => 10000,
        'minimum_stock' => 100,
    ]);

    OutletInventory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'ingredient_id' => $ingredient->id,
        'product_id' => null,
        'quantity' => 10000,
        'unit' => 'ml',
    ]);

    $productA = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Matcha Latte',
        'unit' => 'pcs',
        'selling_price' => 25000,
        'recipe_type' => Product::RECIPE_UNIT,
        'is_active' => true,
    ]);
    RecipeItem::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productA->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 200,
    ]);

    $productB = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kopi Susu',
        'unit' => 'pcs',
        'selling_price' => 22000,
        'recipe_type' => Product::RECIPE_UNIT,
        'is_active' => true,
    ]);
    RecipeItem::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productB->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 150,
    ]);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [
            ['product_id' => $productA->id, 'quantity' => 2],
            ['product_id' => $productB->id, 'quantity' => 1],
        ],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 100000,
    );

    // Total: 25000×2 + 22000×1 = 72000
    expect((float) $order->total)->toBe(72000.0);

    // Two separate sales created
    $sales = Sale::where('pos_order_id', $order->id)->get();
    expect($sales)->toHaveCount(2);

    // Outlet inventory: 10000 − (200×2 + 150×1) = 10000 − 550 = 9450
    $inventory = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();
    expect((float) $inventory->quantity)->toBe(9450.0);
});

/*
|--------------------------------------------------------------------------
| 2) voidSale reverses stock + marks sale voided
|--------------------------------------------------------------------------
*/

it('void reverses outlet stock and marks order + sales voided', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithOutletStock(
        $this->outlet, $this->tenant, 5000
    );

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 2]],
        paymentMethod: PosOrder::PAYMENT_QRIS,
        amountPaid: 50000,
    );

    // Verify stock was deducted
    $inventory = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();
    expect((float) $inventory->quantity)->toBe(4600.0);

    // Void the order
    posService()->void($order, $this->session);

    // Order status updated
    $order->refresh();
    expect($order->status)->toBe(PosOrder::STATUS_VOID);

    // Sale status updated
    $sale = Sale::where('pos_order_id', $order->id)->first();
    expect($sale->status)->toBe(Sale::STATUS_VOID);

    // Outlet stock restored: 4600 + 400 = 5000
    $inventory->refresh();
    expect((float) $inventory->quantity)->toBe(5000.0);
});

it('void decrements session totals', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 25000,
    );

    $this->session->refresh();
    expect((float) $this->session->total_cash)->toBe(25000.0);

    posService()->void($order, $this->session);

    $this->session->refresh();
    expect((float) $this->session->total_cash)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| 3) Insufficient stock handling
|--------------------------------------------------------------------------
*/

it('allows checkout with insufficient stock (negative stock permitted)', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithOutletStock(
        $this->outlet, $this->tenant, 100 // only 100ml, recipe needs 200ml
    );

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 25000,
    );

    expect($order->status)->toBe(PosOrder::STATUS_COMPLETED);

    // Stock goes negative: 100 − 200 = −100
    $inventory = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();
    expect((float) $inventory->quantity)->toBe(-100.0);
});

it('throws when payment amount is less than total (cash)', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 2]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 10000, // total is 50000
    );
})->throws(InvalidArgumentException::class, 'Nominal tunai kurang dari total.');

it('throws when session is closed', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    $this->session->update(['closed_at' => now()]);

    posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 25000,
    );
})->throws(InvalidArgumentException::class, 'Sesi kasir sudah ditutup.');

it('throws when cart is empty', function () {
    posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 0,
    );
})->throws(InvalidArgumentException::class, 'Keranjang kosong.');

it('throws when voiding already-voided order', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_QRIS,
        amountPaid: 25000,
    );

    posService()->void($order, $this->session);

    // Second void should throw
    posService()->void($order->fresh(), $this->session);
})->throws(InvalidArgumentException::class, 'Transaksi sudah di-void.');

it('throws when voiding with wrong session', function () {
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_QRIS,
        amountPaid: 25000,
    );

    // Create a different session
    $otherSession = CashierSession::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'total_cash' => 0,
        'total_qris' => 0,
        'total_transfer' => 0,
        'opened_at' => now(),
    ]);

    posService()->void($order, $otherSession);
})->throws(InvalidArgumentException::class, 'Transaksi bukan dari sesi aktif.');

/*
|--------------------------------------------------------------------------
| 4) Sale with recipe-based COGS
|--------------------------------------------------------------------------
*/

it('snapshots recipe-based COGS on each sale', function () {
    // Ingredient: Rp20/ml, recipe: 200ml/unit → COGS = 4000
    ['product' => $product] = createUnitProductWithOutletStock($this->outlet, $this->tenant);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 25000,
    );

    $sale = Sale::where('pos_order_id', $order->id)->first();

    expect((float) $sale->revenue)->toBe(25000.0)
        ->and((float) $sale->cogs)->toBe(4000.0)   // 200ml × Rp20
        ->and((float) $sale->profit)->toBe(21000.0)  // 25000 − 4000
        ->and((float) $sale->margin)->toBe(84.0);    // (25000−4000)/25000 × 100
});

it('calculates COGS correctly for multiple ingredients', function () {
    $susu = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 20,
        'current_stock' => 5000,
        'minimum_stock' => 100,
    ]);

    $matcha = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Matcha Powder',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'weight',
        'base_unit' => 'g',
        'unit_price' => 100,
        'current_stock' => 1000,
        'minimum_stock' => 50,
    ]);

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Premium Matcha Latte',
        'unit' => 'pcs',
        'selling_price' => 35000,
        'recipe_type' => Product::RECIPE_UNIT,
        'is_active' => true,
    ]);

    RecipeItem::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'ingredient_id' => $susu->id,
        'quantity' => 200, // 200ml × Rp20 = Rp4000
    ]);

    RecipeItem::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'ingredient_id' => $matcha->id,
        'quantity' => 10, // 10g × Rp100 = Rp1000
    ]);

    // Seed outlet inventory for both ingredients
    OutletInventory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'ingredient_id' => $susu->id,
        'product_id' => null,
        'quantity' => 5000,
        'unit' => 'ml',
    ]);

    OutletInventory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'ingredient_id' => $matcha->id,
        'product_id' => null,
        'quantity' => 1000,
        'unit' => 'g',
    ]);

    $order = posService()->checkout(
        session: $this->session,
        user: $this->user,
        lineItems: [['product_id' => $product->id, 'quantity' => 3]],
        paymentMethod: PosOrder::PAYMENT_TUNAI,
        amountPaid: 105000,
    );

    // Revenue: 35000 × 3 = 105000
    expect((float) $order->total)->toBe(105000.0);

    $sale = Sale::where('pos_order_id', $order->id)->first();

    // COGS per unit: (200×20) + (10×100) = 4000 + 1000 = 5000
    // Total COGS: 5000 × 3 = 15000
    // Profit: 105000 − 15000 = 90000
    expect((float) $sale->cogs)->toBe(15000.0)
        ->and((float) $sale->profit)->toBe(90000.0)
        ->and((float) $sale->margin)->toBe(round(90000 / 105000 * 100, 2));

    // Both ingredients deducted: susu 200×3=600, matcha 10×3=30
    $susuInv = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $susu->id)->first();
    $matchaInv = OutletInventory::where('outlet_id', $this->outlet->id)
        ->where('ingredient_id', $matcha->id)->first();

    expect((float) $susuInv->quantity)->toBe(4400.0)  // 5000 − 600
        ->and((float) $matchaInv->quantity)->toBe(970.0); // 1000 − 30
});
