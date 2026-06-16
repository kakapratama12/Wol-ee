# ADR-0003: Service layer + stok sebagai ledger

- Status: Accepted
- Tanggal: 2026-06-16
- Pengambil keputusan: tim Wol-ee

## Konteks

Logika bisnis (COGS, penjualan, mutasi stok, pajak) dipakai oleh dua entry point:
web dashboard (Inertia) dan API bot. Stok harus konsisten walau diubah dari
penjualan, pembelian, atau penyesuaian manual, dan harus bisa diaudit.

## Keputusan

- **Service layer** (`app/Services`) memegang seluruh logika bisnis. Controller
  tipis dan hanya mendelegasikan. Web dan API memakai service yang sama.
- **Stok sebagai ledger**: setiap perubahan stok menulis baris `stock_movements`;
  `ingredients.current_stock` adalah nilai turunan yang di-update dalam transaksi
  database yang sama (`DB::transaction()`), sehingga atomic.
- Penjualan menyimpan **snapshot COGS** saat transaksi agar laporan historis tidak
  berubah ketika harga bahan berubah.

## Alternatif yang dipertimbangkan

- **Logika di controller** — ditolak: duplikasi antara web & API, sulit ditest.
- **Stok sebagai angka tunggal tanpa ledger** — ditolak: tidak bisa diaudit,
  rawan race condition dan drift.

## Konsekuensi

- Positif: satu sumber logika, mudah ditest (unit/feature), stok auditable.
- Trade-off: lebih banyak penulisan baris (movements); perlu disiplin selalu
  lewat service, bukan update model langsung.
