<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'ingredient_id',
        'payable_id',
        'quantity',
        'unit_price',
        'total',
        'source',
        'note',
        'occurred_at',
        'tenant_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
