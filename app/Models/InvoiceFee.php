<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceFee extends Model
{
    use HasFactory;

    protected \ = [
        'invoice_id',
        'name',
        'type',
        'value',
        'amount',
    ];

    protected \ = [
        'value' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function invoice(): BelongsTo
    {
        return \->belongsTo(Invoice::class);
    }
}
