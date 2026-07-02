<?php

use App\Models\Branch;
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
    $this->branch = Branch::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Bandung',
        'is_active' => true,
    ]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'role' => User::ROLE_CASHIER,
        'email_verified_at' => now(),
    ]);
});

function createUnitProductWithStock(Tenant $tenant, float $stock = 5000): array
{
    $ingredient = Ingredient::create([
        'tenant_id' => $tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'gramasi',
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

    return compact('ingredient', 'product');
}

it('kasir bisa buka sesi dan checkout tunai', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant);

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

it('checkout gagal dengan pesan per produk tanpa nama bahan', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithStock($this->tenant, 300);

    $this->actingAs($this->cashier)
        ->postJson('/pos/session/open', ['opening_cash' => 0]);

    $response = $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => PosOrder::PAYMENT_TUNAI,
        'amount_paid' => 100000,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 'CART_UNAVAILABLE')
        ->assertJsonPath('unavailable_products.0.name', 'Matcha Latte');

    expect($response->json('message'))->not->toContain($ingredient->name);
    expect(Sale::count())->toBe(0);
    expect(PosOrder::count())->toBe(0);
});

it('estimate max portions untuk unit product', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant, 1000);

    $service = app(ProductAvailabilityService::class);

    expect($service->estimateMaxPortions($product, $this->branch->id))->toBe(5);
});

it('void pos order soft void sales dan kembalikan stok', function () {
    ['product' => $product, 'ingredient' => $ingredient] = createUnitProductWithStock($this->tenant, 5000);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 0]);
    $orderResponse = $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => PosOrder::PAYMENT_QRIS,
        'amount_paid' => 50000,
    ])->assertCreated();

    $orderId = $orderResponse->json('order.id');
    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(4600.0);

    $this->postJson("/pos/orders/{$orderId}/void")->assertOk();

    expect(Sale::query()->where('status', Sale::STATUS_VOID)->count())->toBe(1);
    $ingredient->refresh();
    expect((float) $ingredient->current_stock)->toBe(5000.0);
});

it('tutup sesi menghitung selisih kas', function () {
    ['product' => $product] = createUnitProductWithStock($this->tenant);

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
    ['product' => $product] = createUnitProductWithStock($this->tenant);

    $this->actingAs($this->cashier);
    $this->postJson('/pos/session/open', ['opening_cash' => 0]);
    $this->postJson('/pos/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => PosOrder::PAYMENT_QRIS,
        'amount_paid' => 25000,
    ])->assertCreated();

    $order = PosOrder::first();
    $sale = Sale::first();

    expect($order->branch_id)->toBe($this->branch->id);
    expect($sale->branch_id)->toBe($this->branch->id);
});

it('checkout multi produk dalam satu order', function () {
    $ingredient = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Susu',
        'item_type' => Ingredient::ITEM_RAW_MATERIAL,
        'unit_type' => 'gramasi',
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

it('pengelola tidak bisa akses pos routes', function () {
    $pengelola = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => User::ROLE_PENGELOLA,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pengelola)
        ->getJson('/pos/session/status')
        ->assertRedirect(route('dashboard'));
});
