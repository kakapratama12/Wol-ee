<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_MODAL_AWAL = 'modal_awal';
    public const TYPE_MODAL_TAMBAHAN = 'modal_tambahan';
    public const TYPE_LAINNYA = 'lainnya';

    public const TYPES = [
        self::TYPE_MODAL_AWAL => 'Modal Awal',
        self::TYPE_MODAL_TAMBAHAN => 'Modal Tambahan',
        self::TYPE_LAINNYA => 'Lainnya',
    ];

    protected $fillable = [
        'type',
        'amount',
        'description',
        'occurred_at',
        'tenant_id',
        'outlet_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'date',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
