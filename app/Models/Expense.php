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
    public const CATEGORY_LOGISTIK = 'logistik';
    public const CATEGORY_OVERHEAD = 'overhead';
    public const CATEGORY_NON_OPERASIONAL = 'non_operasional';

    public const CATEGORIES = [
        self::CATEGORY_BAHAN_BAKU => 'Bahan Baku',
        self::CATEGORY_OPERASIONAL => 'Operasional',
        self::CATEGORY_LOGISTIK => 'Logistik/Pengiriman',
        self::CATEGORY_OVERHEAD => 'Overhead',
        self::CATEGORY_NON_OPERASIONAL => 'Di Luar Usaha',
    ];

    /** Kategori yang masuk P&L (bukan non-operasional). */
    public const PNL_CATEGORIES = [
        self::CATEGORY_BAHAN_BAKU,
        self::CATEGORY_OPERASIONAL,
        self::CATEGORY_LOGISTIK,
        self::CATEGORY_OVERHEAD,
    ];

    /** Deskripsi per kategori untuk tooltip UI. */
    public const CATEGORY_DESCRIPTIONS = [
        self::CATEGORY_BAHAN_BAKU => 'Bahan baku langsung untuk produksi (tepung, gula, dsb)',
        self::CATEGORY_OPERASIONAL => 'Biaya operasional harian (listrik, air, gaji, sewa)',
        self::CATEGORY_LOGISTIK => 'Biaya pengiriman ke customer (ongkir, commission platform)',
        self::CATEGORY_OVERHEAD => 'Biaya tidak langsung (amortisasi alat, perlengkapan kantor)',
        self::CATEGORY_NON_OPERASIONAL => 'Biaya di luar kegiatan usaha (pribadi, non-bisnis)',
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
