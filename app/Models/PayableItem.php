<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableItem extends Model
{
    protected $fillable = [
        'payable_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
