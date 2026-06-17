<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBotAiRequestRequest;
use App\Http\Support\ApiResponse;
use App\Models\BotAiRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class BotAiRequestController extends Controller
{
    public function store(StoreBotAiRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $row = BotAiRequest::create([
            'tenant_id' => $tenant->id,
            'telegram_user_id' => (int) $data['telegram_user_id'],
            'plan' => $data['plan'],
            'provider' => $data['provider'],
            'model' => $data['model'] ?? null,
            'status' => $data['status'],
            'error_code' => $data['error_code'] ?? null,
            'latency_ms' => $data['latency_ms'] ?? null,
            'prompt_tokens' => $data['prompt_tokens'] ?? null,
            'completion_tokens' => $data['completion_tokens'] ?? null,
            'total_tokens' => $data['total_tokens'] ?? null,
            'requested_at' => $data['requested_at'] ?? now(),
        ]);

        return ApiResponse::success('AI request dicatat.', [
            'id' => $row->id,
        ], 201);
    }
}
