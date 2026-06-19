<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotFeedback extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'bot_feedbacks';

    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'original_message',
        'feedback_text',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
