<?php

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function owner(): User
{
    $tenant = Tenant::factory()->create();

    return User::factory()->create([
        'role' => User::ROLE_PENGELOLA,
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);
}

function staff(): User
{
    $tenant = Tenant::factory()->create();

    return User::factory()->create([
        'role' => User::ROLE_STAFF,
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);
}

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('owner bisa mengakses semua halaman utama', function () {
    $this->actingAs(owner());

    foreach (['/dashboard', '/inventory', '/transactions', '/sales', '/partners', '/invoices', '/products', '/tax', '/pnl', '/expenses', '/margin', '/settings/bot', ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('admin bisa akses inventory & transaksi tapi tidak halaman owner', function () {
    $this->actingAs(staff());

    $this->get('/inventory')->assertOk();
    // transactions: owner middleware (staff tidak boleh akses)
    // sales: owner middleware (staff tidak boleh akses)
    $this->get('/partners')->assertOk();
    $this->get('/invoices')->assertOk();

    foreach (['/products', '/tax', '/pnl', '/expenses', '/margin'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('admin tidak bisa membuat bahan (owner only)', function () {
    $this->actingAs(staff());

    $this->post('/inventory', [
        'name' => 'Tepung',
        'unit_type' => 'weight',
        'base_unit' => 'g',
        'unit_price' => 20,
        'minimum_stock' => 100,
    ])->assertForbidden();
});

it('menampilkan chart bulanan dan pembelian terbaru di dashboard', function () {
    $user = owner();
    $this->actingAs($user);

    $ingredient = Ingredient::create([
        'tenant_id' => $user->tenant_id,
        'name' => 'Susu',
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 20,
        'current_stock' => 1000,
        'minimum_stock' => 500,
    ]);
    $product = Product::create([
        'tenant_id' => $user->tenant_id,
        'name' => 'Kopi Susu',
        'unit' => 'cup',
        'selling_price' => 18000,
    ]);

    Sale::create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 18000,
        'revenue' => 36000,
        'cogs' => 10000,
        'profit' => 26000,
        'margin' => 72.22,
        'source' => 'bot',
        'occurred_at' => now(),
    ]);
    Transaction::create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1000,
        'unit_price' => 20,
        'total' => 20000,
        'source' => 'bot',
        'occurred_at' => now(),
    ]);
    Expense::create([
        'tenant_id' => $user->tenant_id,
        'category' => 'Sewa',
        'description' => 'Sewa outlet',
        'amount' => 500000,
        'period_month' => now()->month,
        'period_year' => now()->year,
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recentPurchases', 1)
            ->where('recentPurchases.0.ingredient', 'Susu')
            ->where('recentPurchases.0.source', 'bot')
            ->has('monthlyChart', 6)
            ->where('monthlyChart.5.revenue', 36000)
            ->where('monthlyChart.5.expense', 500000)
        );
});
