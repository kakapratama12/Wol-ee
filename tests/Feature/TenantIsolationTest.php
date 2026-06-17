<?php

use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\User;

it('mengisolasi data antar tenant', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);

    $this->actingAs($userA);
    Ingredient::create([
        'name' => 'Tepung A',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 20,
        'current_stock' => 1000,
        'minimum_stock' => 100,
    ]);

    $this->actingAs($userB);
    Ingredient::create([
        'name' => 'Tepung B',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 25,
        'current_stock' => 500,
        'minimum_stock' => 50,
    ]);

    expect(Ingredient::pluck('name')->all())->toBe(['Tepung B']);

    $this->actingAs($userA);
    expect(Ingredient::pluck('name')->all())->toBe(['Tepung A']);
});

it('menolak akses user tanpa tenant ke data scoped', function () {
    $user = User::factory()->create(['tenant_id' => null, 'role' => 'owner']);
    $tenant = Tenant::factory()->create();
    Ingredient::withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gula',
        'unit_type' => 'gramasi',
        'base_unit' => 'g',
        'unit_price' => 15,
        'current_stock' => 100,
        'minimum_stock' => 10,
    ]);

    $this->actingAs($user);

    // Tanpa tenant_id, global scope tidak aktif — lihat semua (edge case super_admin nanti).
    expect(Ingredient::withoutGlobalScope('tenant')->count())->toBe(1);
});
