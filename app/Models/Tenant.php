<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_BUSINESS = 'business';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'bot_token',
    ];

    protected $hidden = [
        'bot_token',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(BotFeedback::class);
    }

    public function aiUsages(): HasMany
    {
        return $this->hasMany(BotAiUsage::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
