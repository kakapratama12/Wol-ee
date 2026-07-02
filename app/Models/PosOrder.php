<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOID = 'void';

    public const PAYMENT_TUNAI = 'tunai';
    public const PAYMENT_QRIS = 'qris';
    public const PAYMENT_TRANSFER = 'transfer';

    protected $fillable = [
        'tenant_id',
        'cashier_session_id',
        'outlet_id',
        'user_id',
        'total',
        'payment_method',
        'amount_paid',
        'change_amount',
        'status',
        'note',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function cashierSession(): BelongsTo
    {
        return $this->belongsTo(CashierSession::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }
}
