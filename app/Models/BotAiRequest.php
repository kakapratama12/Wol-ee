<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAiRequest extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_QUOTA_EXCEEDED = 'quota_exceeded';

    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'plan',
        'provider',
        'model',
        'status',
        'error_code',
        'latency_ms',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'latency_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'requested_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
