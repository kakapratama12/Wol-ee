<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Services\BotTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotAuthController extends Controller
{
    public function __construct(private readonly BotTokenService $botTokens) {}

    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $tenant = $this->botTokens->validate($request->string('token')->toString());

        if (! $tenant) {
            return ApiResponse::error('Token tidak valid.', 'UNAUTHORIZED', 401);
        }

        return ApiResponse::success('Token valid.', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
            ],
        ]);
    }
}
