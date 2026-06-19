<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_OUTSTANDING = 'outstanding';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'invoice_number',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'note',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
