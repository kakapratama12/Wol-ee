<?php

namespace App\Http\Middleware;

use App\Http\Support\ApiResponse;
use App\Services\BotTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BotTokenAuth
{
    public function __construct(private readonly BotTokenService $botTokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return ApiResponse::error('Token tidak valid.', 'UNAUTHORIZED', 401);
        }

        $tenant = $this->botTokens->validate($plainToken);

        if (! $tenant) {
            return ApiResponse::error('Token tidak valid.', 'UNAUTHORIZED', 401);
        }

        $owner = $this->botTokens->resolveOwner($tenant);

        if (! $owner) {
            return ApiResponse::error('Owner tenant tidak ditemukan.', 'UNAUTHORIZED', 401);
        }

        auth()->login($owner);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
