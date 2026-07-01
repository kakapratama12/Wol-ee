<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'product_id',
        'outlet_id',
        'quantity',
        'unit_price',
        'revenue',
        'cogs',
        'profit',
        'margin',
        'source',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
