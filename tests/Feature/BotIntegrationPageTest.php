<?php

use App\Models\Tenant;
use App\Models\User;

it('owner bisa akses halaman bot integration', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'role' => 'owner',
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get('/settings/bot')
        ->assertOk();
});

it('admin tidak bisa akses halaman bot integration', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/settings/bot')
        ->assertForbidden();
});
