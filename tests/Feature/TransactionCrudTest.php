<?php

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'pengelola',
        'email_verified_at' => now(),
    ]);
    $this->staff = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

it('owner bisa update dan delete penjualan', function () {
    $this->actingAs($this->owner);

    $tepung = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tepung',
        'unit_type' => 'weight',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 5000,
        'minimum_stock' => 1000,
    ]);

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Roti',
        'unit' => 'pcs',
        'selling_price' => 5000,
    ]);

    RecipeItem::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'ingredient_id' => $tepung->id,
        'quantity' => 100,
    ]);

    $this->post('/sales', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $sale = Sale::first();

    $this->put("/sales/{$sale->id}", [
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertRedirect()->assertSessionHas('success');

    $this->delete('/sales/'.Sale::first()->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Sale::count())->toBe(1);
    expect(Sale::first()->status)->toBe(Sale::STATUS_VOID);
});

it('owner bisa update dan delete pembelian', function () {
    $this->actingAs($this->owner);

    $ingredient = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Susu',
        'unit_type' => 'weight',
        'base_unit' => 'ml',
        'unit_price' => 18,
        'current_stock' => 0,
        'minimum_stock' => 500,
    ]);

    $this->post('/transactions', [
        'ingredient_id' => $ingredient->id,
        'quantity' => 1000,
        'total' => 18000,
    ])->assertRedirect();

    $transaction = Transaction::first();

    $this->put("/transactions/{$transaction->id}", [
        'ingredient_id' => $ingredient->id,
        'quantity' => 2000,
        'total' => 36000,
    ])->assertRedirect()->assertSessionHas('success');

    $this->delete('/transactions/'.Transaction::first()->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Transaction::count())->toBe(0);
});

it('owner bisa update biaya operasional', function () {
    $this->actingAs($this->owner);

    $this->post('/expenses', [
        'category' => 'operasional',
        'description' => 'PLN',
        'amount' => 500000,
        'period_month' => 6,
        'period_year' => 2026,
    ])->assertRedirect();

    $expense = Expense::first();

    $this->put("/expenses/{$expense->id}", [
        'category' => 'operasional',
        'description' => 'PLN Juni',
        'amount' => 550000,
        'period_month' => 6,
        'period_year' => 2026,
    ])->assertRedirect()->assertSessionHas('success');

    expect((float) $expense->fresh()->amount)->toBe(550000.0);
});

it('staff tidak bisa update biaya operasional', function () {
    $this->actingAs($this->owner);

    $this->post('/expenses', [
        'category' => 'operasional',
        'amount' => 3000000,
        'period_month' => 6,
        'period_year' => 2026,
    ]);

    $expense = Expense::first();

    $this->actingAs($this->staff);

    $this->put("/expenses/{$expense->id}", [
        'category' => 'operasional',
        'amount' => 3500000,
        'period_month' => 6,
        'period_year' => 2026,
    ])->assertForbidden();
});

it('menolak hapus pembelian jika stok tidak cukup', function () {
    $this->actingAs($this->owner);

    $ingredient = Ingredient::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mentega',
        'unit_type' => 'weight',
        'base_unit' => 'g',
        'unit_price' => 50,
        'current_stock' => 0,
        'minimum_stock' => 100,
    ]);

    $this->post('/transactions', [
        'ingredient_id' => $ingredient->id,
        'quantity' => 1000,
        'total' => 50000,
    ])->assertRedirect();

    $transaction = Transaction::first();
    $ingredient->update(['current_stock' => 200]);

    $this->delete("/transactions/{$transaction->id}")
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});
