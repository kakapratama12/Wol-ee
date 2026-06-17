<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BotTokenService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->auth = authenticateBotTenant('admin');
    $this->withHeader('Authorization', 'Bearer '.$this->auth['token']);
});

it('menolak akses tanpa token', function () {
    $this->withHeader('Authorization', '');

    $response = $this->getJson('/api/stock');

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'UNAUTHORIZED');
});

it('mencatat pembelian via API dan menambah stok', function () {
    $ingredient = Ingredient::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 3000,
        'minimum_stock' => 1000,
    ]);

    $response = $this->postJson('/api/transactions', [
        'ingredient' => 'tepung',
        'quantity' => 2000,
        'total' => 40000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.new_stock', 5000)
        ->assertJsonPath('data.unit_price', 20);

    expect((float) $ingredient->fresh()->current_stock)->toBe(5000.0);
});

it('mencatat penjualan via API dengan COGS dan alert', function () {
    $tepung = Ingredient::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 1050,
        'minimum_stock' => 1000,
    ]);
    $product = Product::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Roti Goreng',
        'unit' => 'pcs',
        'selling_price' => 5000,
    ]);
    RecipeItem::create([
        'tenant_id' => $this->auth['tenant']->id,
        'product_id' => $product->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 100,
    ]);

    $response = $this->postJson('/api/sales', [
        'product' => 'Roti Goreng',
        'quantity' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.revenue', 5000)
        ->assertJsonPath('data.cogs', 2000)
        ->assertJsonPath('data.profit', 3000);

    expect($response->json('data.alerts'))->toHaveCount(1)
        ->and($response->json('data.alerts.0.ingredient'))->toBe('Tepung');
});

it('mengembalikan 422 dengan error_code jika produk tidak ditemukan', function () {
    $response = $this->postJson('/api/sales', [
        'product' => 'Produk Hantu',
        'quantity' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'PRODUCT_NOT_FOUND')
        ->assertJsonStructure(['available_items', 'dashboard_url']);
});

it('mencatat batch penjualan via API secara atomic', function () {
    $tepung = Ingredient::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 5000,
        'minimum_stock' => 1000,
    ]);
    $matcha = Product::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Matcha Latte',
        'unit' => 'cup',
        'selling_price' => 25000,
    ]);
    $croissant = Product::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Croissant',
        'unit' => 'pcs',
        'selling_price' => 35000,
    ]);
    RecipeItem::create([
        'tenant_id' => $this->auth['tenant']->id,
        'product_id' => $matcha->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 50,
    ]);
    RecipeItem::create([
        'tenant_id' => $this->auth['tenant']->id,
        'product_id' => $croissant->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 80,
    ]);

    $response = $this->postJson('/api/sales/batch', [
        'items' => [
            ['product' => 'Matcha Latte', 'quantity' => 2],
            ['product' => 'Croissant', 'quantity' => 1],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_revenue', 85000)
        ->assertJsonCount(2, 'data.sales');
});

it('menampilkan daftar stok', function () {
    Ingredient::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Susu',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 18,
        'current_stock' => 3000,
        'minimum_stock' => 5000,
    ]);

    $response = $this->getJson('/api/stock');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.ingredient', 'susu')
        ->assertJsonPath('data.0.status', 'menipis');
});

it('memvalidasi token bot via endpoint validate-token', function () {
    $tenant = Tenant::factory()->create();
    $secret = 'validate-secret-32chars-long!!!!!!';
    $tenant->update(['bot_token' => Hash::make($secret)]);
    $plain = $tenant->id.':'.$secret;

    $valid = $this->postJson('/api/bot/validate-token', ['token' => $plain]);
    $valid->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tenant.name', $tenant->name);

    $invalid = $this->postJson('/api/bot/validate-token', ['token' => '99:invalid']);
    $invalid->assertUnauthorized()
        ->assertJsonPath('message', 'Token tidak valid.');
});

it('menghasilkan token via artisan command', function () {
    $tenant = Tenant::factory()->create();

    $this->artisan('wol-ee:generate-bot-token', ['--tenant' => $tenant->id])
        ->assertSuccessful();

    expect($tenant->fresh()->bot_token)->not->toBeNull();

    $service = app(BotTokenService::class);
    expect($service->validate('invalid'))->toBeNull();
});

it('menampilkan riwayat pembelian via API', function () {
    $ingredient = Ingredient::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Gula',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 15,
        'current_stock' => 1000,
        'minimum_stock' => 500,
    ]);

    $this->postJson('/api/transactions', [
        'ingredient' => 'gula',
        'quantity' => 500,
        'total' => 7500,
    ])->assertCreated();

    $response = $this->getJson('/api/transactions?limit=5');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.ingredient', 'Gula');
});

it('menampilkan riwayat penjualan via API', function () {
    $product = Product::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Es Teh',
        'unit' => 'cup',
        'selling_price' => 8000,
    ]);

    $this->postJson('/api/sales', [
        'product' => 'Es Teh',
        'quantity' => 2,
    ])->assertCreated();

    $response = $this->getJson('/api/sales?limit=5');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.product', 'Es Teh');
});

it('menampilkan daftar produk untuk konteks bot', function () {
    Product::create([
        'tenant_id' => $this->auth['tenant']->id,
        'name' => 'Matcha Latte',
        'unit' => 'cup',
        'selling_price' => 45000,
    ]);

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Matcha Latte');
});

