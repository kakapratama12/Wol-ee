<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant, HasFactory;

    public const CATEGORY_BAHAN_BAKU = 'bahan_baku';
    public const CATEGORY_OPERASIONAL = 'operasional';
    public const CATEGORY_OVERHEAD = 'overhead';
    public const CATEGORY_NON_OPERASIONAL = 'non_operasional';

    public const CATEGORIES = [
        self::CATEGORY_BAHAN_BAKU => 'Bahan Baku',
        self::CATEGORY_OPERASIONAL => 'Operasional',
        self::CATEGORY_OVERHEAD => 'Overhead',
        self::CATEGORY_NON_OPERASIONAL => 'Di Luar Usaha',
    ];

    /** Kategori yang masuk P&L (bukan non-operasional). */
    public const PNL_CATEGORIES = [
        self::CATEGORY_BAHAN_BAKU,
        self::CATEGORY_OPERASIONAL,
        self::CATEGORY_OVERHEAD,
    ];

    protected $fillable = [
        'category',
        'description',
        'amount',
        'period_month',
        'period_year',
        'occurred_at',
        'tenant_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
        'occurred_at' => 'datetime',
    ];
}
