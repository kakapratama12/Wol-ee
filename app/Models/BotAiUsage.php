<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAiUsage extends Model
{
    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'usage_date',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'telegram_user_id' => 'integer',
            'count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
