<?php

use App\Models\Outlet;
use App\Models\OutletInventory;
use App\Models\CashierSession;
use App\Models\Ingredient;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductAvailabilityService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Outlet::create([
        'type' => 'primary',
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Bandung',
        'is_active' => true,
    ]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->branch->id,
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

/**
 * Helper: create ingredient + product + recipe + outlet_inventory.
 * POS stock lives in outlet_inventory, not ingredient.current_stock.
 */
function createUnitProductWithStock(Tenant $tenant, Outlet $branch, float $stock = 5000): array
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
        'quantity' => 200,
    ]);

    // POS reads stock from outlet_inventory, not ingredient.current_stock
    OutletInventory::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $branch->id,
        'ingredient_id' => $ingredient->id,
        'product_id' => null,
        'quantity' => $stock,
        'unit' => 'ml',
    ]);

    return compact('ingredient', 'product');
}

it('kasir bisa buka sesi dan checkout tunai', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant, $this->branch);

    $this->actingAs($this->cashier)
        ->postJson('/pos/session/open', ['opening_cash' => 100000])
        ->assertCreated()
        ->assertJsonPath('session.id', fn ($id) => $id > 0);

    $response = $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => PosOrder::PAYMENT_TUNAI,
        'amount_paid' => 100000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('order.total', 50000)
        ->assertJsonPath('order.change_amount', 50000);

    expect(Sale::query()->where('source', Sale::SOURCE_POS)->count())->toBe(1);
    expect(PosOrder::count())->toBe(1);
});

it('checkout gagal jika produk tidak ditemukan', function () {
    $this->actingAs($this->cashier)
        ->postJson('/pos/session/open', ['opening_cash' => 0]);

    $response = $this->postJson('/pos/orders', [
        'items' => [['product_id' => 99999, 'quantity' => 2]],
        'payment_method' => PosOrder::PAYMENT_TUNAI,
        'amount_paid' => 100000,
    ]);

    // validateCart throws InvalidArgumentException for product not found
    $response->assertStatus(422);
    expect(Sale::count())->toBe(0);
    expect(PosOrder::count())->toBe(0);
});

it('estimate max portions untuk unit product', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant, $this->branch, 1000);

    $service = app(ProductAvailabilityService::class);

    // 1000ml stock / 200ml per recipe = 5 portions
    expect($service->estimateMaxPortions($product, $this->branch->id))->toBe(5);
});

it('void pos order soft void sales dan kembalikan stok', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithStock($this->tenant, $this->branch, 5000);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 0]);
    $orderResponse = $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => PosOrder::PAYMENT_QRIS,
        'amount_paid' => 50000,
    ])->assertCreated();

    $orderId = $orderResponse->json('order.id');

    // POS deducts from outlet_inventory
    $inventory = OutletInventory::where('outlet_id', $this->branch->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();
    expect((float) $inventory->quantity)->toBe(4600.0);

    $this->postJson("/pos/orders/{$orderId}/void")->assertOk();

    expect(Sale::query()->where('status', Sale::STATUS_VOID)->count())->toBe(1);
    $inventory->refresh();
    expect((float) $inventory->quantity)->toBe(5000.0);
});

it('tutup sesi menghitung selisih kas', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant, $this->branch);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 50000]);
    $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => PosOrder::PAYMENT_TUNAI,
        'amount_paid' => 25000,
    ]);

    $response = $this->postJson('/pos/session/close', ['actual_cash' => 75000]);

    $response->assertOk()
        ->assertJsonPath('summary.variance', 0);

    expect(CashierSession::first()->closed_at)->not->toBeNull();
});

it('penjualan pos tercatat branch_id dari sesi kasir', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant, $this->branch);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 0]);
    $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => PosOrder::PAYMENT_QRIS,
        'amount_paid' => 25000,
    ])->assertCreated();

    $order = PosOrder::first();
    $sale = Sale::first();

    expect($order->outlet_id)->toBe($this->branch->id);
    expect($sale->outlet_id)->toBe($this->branch->id);
});

it('checkout multi produk dalam satu order', function () {
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

    // Multi-product also needs outlet_inventory
    OutletInventory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'product_id' => null,
        'quantity' => 10000,
        'unit' => 'ml',
    ]);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 0]);
    $this->postJson('/pos/orders', [
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 2],
            ['product_id' => $productB->id, 'quantity' => 1],
        ],
        'payment_method' => PosOrder::PAYMENT_TUNAI,
        'amount_paid' => 100000,
    ])->assertCreated();

    expect(Sale::query()->where('source', Sale::SOURCE_POS)->count())->toBe(2);
    Sale::all()->each(fn (Sale $sale) => expect(strlen((string) $sale->idempotency_key))->toBeLessThanOrEqual(36));
});

it('pengelola multi-outlet tidak bisa akses pos routes', function () {
    $this->tenant->update(['business_type' => 'multi']);
    $pengelola = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => User::ROLE_PENGELOLA,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pengelola)
        ->getJson('/pos/session/status')
        ->assertRedirect(route('dashboard'));
});
