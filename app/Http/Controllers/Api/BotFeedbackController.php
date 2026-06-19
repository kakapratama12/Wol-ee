<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\BotFeedback;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'feedback_text' => ['required', 'string', 'min:3', 'max:2000'],
            'original_message' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $feedback = BotFeedback::create([
            'tenant_id' => $tenant->id,
            'telegram_user_id' => (int) $data['telegram_user_id'],
            'feedback_text' => trim((string) $data['feedback_text']),
            'original_message' => isset($data['original_message']) ? trim((string) $data['original_message']) : null,
            'status' => BotFeedback::STATUS_NEW,
        ]);

        return ApiResponse::success('Feedback dicatat.', [
            'id' => $feedback->id,
            'status' => $feedback->status,
        ], 201);
    }
}
