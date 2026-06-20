<?php

namespace App\Services;

use App\Models\CashEntry;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class CashflowService
{
    /**
     * Laporan arus kas untuk satu bulan.
     *
     * @return array<string, mixed>
     */
    public function report(int $month, int $year): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Saldo awal = saldo akhir bulan sebelumnya
        $saldoAwalBulan = $this->getSaldoAkhir($start->copy()->subMonth()->month, $start->copy()->subMonth()->year);

        // ── Kas Masuk ──
        $penjualan = (float) Sale::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('revenue');

        $modalMasuk = (float) CashEntry::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        $totalKasMasuk = round($penjualan + $modalMasuk, 2);

        // ── Kas Keluar ──
        $pembelian = (float) Transaction::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('total');

        $biayaOperasional = (float) Expense::query()
            ->whereBetween('period_month', $month)
            ->where('period_year', $year)
            ->whereIn('category', Expense::PNL_CATEGORIES)
            ->sum('amount');

        $diLuarUsaha = (float) Expense::query()
            ->whereBetween('period_month', $month)
            ->where('period_year', $year)
            ->where('category', Expense::CATEGORY_NON_OPERASIONAL)
            ->sum('amount');

        $totalKasKeluar = round($pembelian + $biayaOperasional + $diLuarUsaha, 2);

        $saldoAkhir = round($saldoAwalBulan + $totalKasMasuk - $totalKasKeluar, 2);

        // Detail kas masuk
        $kasMasukDetail = [
            'penjualan' => round($penjualan, 2),
            'modal' => round($modalMasuk, 2),
        ];

        // Detail kas keluar
        $kasKeluarDetail = [
            'pembelian' => round($pembelian, 2),
            'biaya_operasional' => round($biayaOperasional, 2),
            'di_luar_usaha' => round($diLuarUsaha, 2),
        ];

        return [
            'month' => $month,
            'year' => $year,
            'saldo_awal' => $saldoAwalBulan,
            'kas_masuk' => $kasMasukDetail,
            'total_kas_masuk' => $totalKasMasuk,
            'kas_keluar' => $kasKeluarDetail,
            'total_kas_keluar' => $totalKasKeluar,
            'saldo_akhir' => $saldoAkhir,
        ];
    }

    /**
     * Hitung saldo akhir sampai akhir bulan tertentu (akumulasi dari awal).
     */
    public function getSaldoAkhir(int $month, int $year): float
    {
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Total kas masuk dari awal sampai akhir bulan ini
        $totalMasuk = (float) Sale::query()
            ->where('occurred_at', '<=', $end)
            ->sum('revenue');

        $totalModal = (float) CashEntry::query()
            ->where('occurred_at', '<=', $end)
            ->sum('amount');

        // Total kas keluar dari awal sampai akhir bulan ini
        $totalPembelian = (float) Transaction::query()
            ->where('occurred_at', '<=', $end)
            ->sum('total');

        $totalBiaya = (float) Expense::query()
            ->where('period_year', '<=', $year)
            ->where(function ($q) use ($year, $month) {
                $q->where('period_year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('period_year', $year)
                            ->where('period_month', '<=', $month);
                    });
            })
            ->sum('amount');

        return round($totalMasuk + $totalModal - $totalPembelian - $totalBiaya, 2);
    }
}
