<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_OUTSTANDING = 'outstanding';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'idempotency_key',
        'tenant_id',
        'partner_id',
        'invoice_number',
        'po_number',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'note',
        'paid_at',
        'archived_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(InvoiceFee::class);
    }
}
