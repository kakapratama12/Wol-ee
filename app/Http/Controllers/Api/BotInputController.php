<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\BotInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotInputController extends Controller
{
    /**
     * Log a bot input (called by bot after successful creation).
     *
     * POST /api/bot-inputs
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'telegram_user_id' => ['required', 'integer'],
            'entity_type' => ['required', 'string', 'in:product,ingredient,recipe,transaction,sale,invoice,partner,expense'],
            'entity_id' => ['nullable', 'integer'],
            'raw_input' => ['required', 'string', 'max:2000'],
            'parsed_data' => ['required', 'array'],
        ]);

        $botInput = BotInput::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$validated,
            'status' => BotInput::STATUS_ACTIVE,
        ]);

        return ApiResponse::success('Bot input logged.', [
            'id' => $botInput->id,
        ], 201);
    }

    /**
     * List bot inputs for dashboard.
     *
     * GET /api/bot-inputs
     */
    public function index(Request $request): JsonResponse
    {
        $query = BotInput::query()->latest();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->where('status', BotInput::STATUS_ACTIVE);
        }

        $inputs = $query->limit(100)->get()->map(fn (BotInput $input) => [
            'id' => $input->id,
            'entity_type' => $input->entity_type,
            'entity_id' => $input->entity_id,
            'summary' => $input->summary(),
            'raw_input' => $input->raw_input,
            'parsed_data' => $input->parsed_data,
            'telegram_user_id' => $input->telegram_user_id,
            'created_at' => $input->created_at->toIso8601String(),
        ]);

        return ApiResponse::success('Riwayat input bot.', $inputs->values()->all());
    }

    /**
     * Archive a bot input (hide from active view).
     *
     * PUT /api/bot-inputs/{botInput}/archive
     */
    public function archive(BotInput $botInput): JsonResponse
    {
        $botInput->update(['status' => BotInput::STATUS_ARCHIVED]);

        return ApiResponse::success('Input diarsipkan.');
    }
}
