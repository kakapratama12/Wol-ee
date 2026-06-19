<?php

namespace App\Http\Requests\Api;

use App\Models\BotAiRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBotAiRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'plan' => ['required', 'string', Rule::in(array_keys(config('ai.plans', [])))],
            'provider' => ['required', 'string', Rule::in(array_keys(config('ai.providers', [])))],
            'model' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([
                BotAiRequest::STATUS_SUCCESS,
                BotAiRequest::STATUS_ERROR,
                BotAiRequest::STATUS_QUOTA_EXCEEDED,
            ])],
            'error_code' => ['nullable', 'string', 'max:255'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
            'prompt_tokens' => ['nullable', 'integer', 'min:0'],
            'completion_tokens' => ['nullable', 'integer', 'min:0'],
            'total_tokens' => ['nullable', 'integer', 'min:0'],
            'requested_at' => ['nullable', 'date'],
        ];
    }
}
