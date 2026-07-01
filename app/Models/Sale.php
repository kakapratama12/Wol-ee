<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_VOID = 'void';

    public const SOURCE_WEB = 'web';
    public const SOURCE_BOT = 'bot';
    public const SOURCE_POS = 'pos';

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'product_id',
        'pos_order_id',
        'branch_id',
        'quantity',
        'unit_price',
        'revenue',
        'cogs',
        'profit',
        'margin',
        'source',
        'status',
        'note',
        'occurred_at',
        'tenant_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'revenue' => 'decimal:2',
        'cogs' => 'decimal:2',
        'profit' => 'decimal:2',
        'margin' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    /**
     * @param  Builder<Sale>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }
}
