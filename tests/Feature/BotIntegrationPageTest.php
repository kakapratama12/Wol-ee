<?php

use App\Models\Tenant;
use App\Models\User;

it('pengelola bisa akses halaman bot integration', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'role' => User::ROLE_PENGELOLA,
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get('/settings/bot')
        ->assertOk();
});

it('staff tidak bisa akses halaman bot integration', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'role' => User::ROLE_STAFF,
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/settings/bot')
        ->assertForbidden();
});
