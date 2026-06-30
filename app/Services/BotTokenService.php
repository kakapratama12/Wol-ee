<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BotTokenService
{
    public function generate(Tenant $tenant): string
    {
        $secret = Str::random(32);
        $plainToken = $tenant->id.':'.$secret;

        $tenant->update(['bot_token' => Hash::make($secret)]);

        return $plainToken;
    }

    public function validate(string $plainToken): ?Tenant
    {
        if (! str_contains($plainToken, ':')) {
            return null;
        }

        [$tenantId, $secret] = explode(':', $plainToken, 2);

        if ($tenantId === '' || $secret === '') {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant || ! $tenant->isActive() || empty($tenant->bot_token)) {
            return null;
        }

        if (! Hash::check($secret, $tenant->bot_token)) {
            return null;
        }

        return $tenant;
    }

    public function resolveOwner(Tenant $tenant): ?User
    {
        return $tenant->users()
            ->where('role', User::ROLE_PENGELOLA)
            ->first();
    }
}
