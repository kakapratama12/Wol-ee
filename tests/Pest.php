<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Autentikasi user dengan tenant untuk test yang membuat data scoped.
 */
function authenticateTestTenant(string $role = 'pengelola'): User
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => $role === 'owner' ? User::ROLE_PENGELOLA : $role,
        'email_verified_at' => now(),
    ]);
    test()->actingAs($user);

    return $user;
}

/**
 * Autentikasi API bot dengan token per-tenant.
 *
 * @return array{tenant: Tenant, user: User, token: string}
 */
function authenticateBotTenant(string $role = 'pengelola'): array
{
    $tenant = Tenant::factory()->create();

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_PENGELOLA,
        'email_verified_at' => now(),
    ]);

    $user = $role === User::ROLE_PENGELOLA || $role === 'owner' || $role === 'pengelola'
        ? $owner
        : User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role === 'admin' ? User::ROLE_STAFF : $role,
            'email_verified_at' => now(),
        ]);
    $secret = 'test-bot-secret-32chars-long!!!!!!';
    $tenant->update(['bot_token' => Hash::make($secret)]);
    $token = $tenant->id.':'.$secret;

    return compact('tenant', 'user', 'token');
}
