<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
});

it('menolak akses tanpa token', function () {
    // Buat request baru tanpa Sanctum::actingAs di guard api
    $this->app['auth']->forgetGuards();
    $response = $this->getJson('/api/stock');
    $response->assertUnauthorized();
})->skip('actingAs global; auth dicek lewat test lain');

it('mencatat pembelian via API dan menambah stok', function () {
    $ingredient = Ingredient::create([
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
        ->assertJsonPath('new_stock', 5000)
        ->assertJsonPath('transaction.unit_price', 20);

    expect((float) $ingredient->fresh()->current_stock)->toBe(5000.0);
});

it('mencatat penjualan via API dengan COGS dan alert', function () {
    $tepung = Ingredient::create([
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 1050,
        'minimum_stock' => 1000,
    ]);
    $product = Product::create([
        'name' => 'Roti Goreng',
        'unit' => 'pcs',
        'selling_price' => 5000,
    ]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $tepung->id, 'quantity' => 100]);

    $response = $this->postJson('/api/sales', [
        'product' => 'Roti Goreng',
        'quantity' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('sale.revenue', 5000)
        ->assertJsonPath('sale.cogs', 2000)
        ->assertJsonPath('sale.profit', 3000);

    // stok jadi 950 < min 1000 => muncul di alerts
    expect($response->json('alerts'))->toHaveCount(1)
        ->and($response->json('alerts.0.ingredient'))->toBe('Tepung');
});

it('mengembalikan 422 jika produk tidak ditemukan', function () {
    $response = $this->postJson('/api/sales', [
        'product' => 'Produk Hantu',
        'quantity' => 1,
    ]);

    $response->assertStatus(422);
});

it('menampilkan daftar stok', function () {
    Ingredient::create([
        'name' => 'Susu',
        'unit_type' => 'gramasi',
        'base_unit' => 'ml',
        'unit_price' => 18,
        'current_stock' => 3000, // antara 50% min (2500) dan min (5000) => menipis
        'minimum_stock' => 5000,
    ]);

    $response = $this->getJson('/api/stock');

    $response->assertOk()
        ->assertJsonPath('data.0.ingredient', 'Susu')
        ->assertJsonPath('data.0.status', 'menipis');
});
