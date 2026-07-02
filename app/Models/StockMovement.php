<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant, HasFactory;

    // Existing types
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_USAGE = 'usage';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSAL = 'reversal';

    // New types for batch model
    public const TYPE_PRODUCTION_INPUT = 'production_input';  // raw materials consumed
    public const TYPE_PRODUCTION_OUTPUT = 'production_output'; // finished goods produced
    public const TYPE_WASTE = 'waste';                          // waste/expired

    // Reasons for adjustment
    public const REASON_RUSAK = 'rusak';
    public const REASON_EXPIRED = 'expired';
    public const REASON_SUSUT = 'susut';
    public const REASON_LAINNYA = 'lainnya';

    public const REASONS = [
        self::REASON_RUSAK => 'Rusak',
        self::REASON_EXPIRED => 'Expired/Kadaluarsa',
        self::REASON_SUSUT => 'Susut/Alami',
        self::REASON_LAINNYA => 'Lainnya',
    ];

    protected $fillable = [
        'ingredient_id',
        'outlet_id',
        'user_id',
        'type',
        'quantity',
        'stock_after',
        'source_type',
        'source_id',
        'production_run_id',
        'note',
        'reason',
        'occurred_at',
        'tenant_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'stock_after' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }
}
