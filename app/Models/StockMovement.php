<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_USAGE = 'usage';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'ingredient_id',
        'type',
        'quantity',
        'stock_after',
        'source_type',
        'source_id',
        'note',
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
}
