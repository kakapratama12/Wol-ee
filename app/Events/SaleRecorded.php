<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan setelah sebuah penjualan berhasil dicatat (transaksi commit).
 * Efek samping non-kritikal (mis. peringatan stok) ditangani listener queued.
 */
class SaleRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Sale $sale) {}
}
