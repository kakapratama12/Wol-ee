<?php

use App\Models\User;

function owner(): User
{
    return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
}

function staff(): User
{
    return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
}

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('owner bisa mengakses semua halaman utama', function () {
    $this->actingAs(owner());

    foreach (['/dashboard', '/inventory', '/transactions', '/sales', '/products', '/tax', '/pnl', '/expenses', '/margin'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('admin bisa akses inventory & transaksi tapi tidak halaman owner', function () {
    $this->actingAs(staff());

    $this->get('/inventory')->assertOk();
    $this->get('/transactions')->assertOk();
    $this->get('/sales')->assertOk();

    foreach (['/products', '/tax', '/pnl', '/expenses', '/margin'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('admin tidak bisa membuat bahan (owner only)', function () {
    $this->actingAs(staff());

    $this->post('/inventory', [
        'name' => 'Tepung',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'minimum_stock' => 100,
    ])->assertForbidden();
});
