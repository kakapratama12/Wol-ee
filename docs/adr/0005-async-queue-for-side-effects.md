# ADR-0005: Queue (database driver) untuk efek samping non-kritikal

- Status: Accepted
- Tanggal: 2026-06-16
- Pengambil keputusan: tim Wol-ee

## Konteks

Beberapa pekerjaan tidak boleh memperlambat request inti. Contoh pertama:
ketika penjualan tercatat dan stok bahan turun ke level menipis/kritis, sistem
harus mengirim peringatan (mis. ke Telegram) — tapi kegagalan/lambatnya notifikasi
tidak boleh menggagalkan atau memperlambat pencatatan penjualan.

## Keputusan

Pakai **queue Laravel dengan driver `database`** sebagai fondasi pekerjaan async.
Pola: domain service memancarkan **event** (mis. `SaleRecorded`), listener yang
`ShouldQueue` menangani efek samping (mis. `SendLowStockAlert`). Worker dijalankan
`queue:work` (Sail saat dev, Supervisor saat produksi).

Driver `database` dipilih dulu agar tidak menambah dependency infrastruktur
(Redis) di tahap awal; jobs table sudah ada dari scaffold.

## Alternatif yang dipertimbangkan

- **Redis + Horizon** — monitoring & throughput lebih baik, tapi menambah service
  yang harus dijalankan/dirawat. Disisihkan sampai volume job menuntutnya.
- **Kerjakan sinkron di request** — ditolak: notifikasi/eksport berat akan
  memperlambat dan membuat request inti rapuh terhadap kegagalan eksternal.

## Konsekuensi

- Positif: request inti tetap cepat & andal; efek samping retryable; tidak ada
  dependency baru.
- Trade-off: perlu worker berjalan (dokumentasikan di deploy); throughput terbatas
  dibanding Redis. Migrasi ke Redis/Horizon mudah karena memakai abstraksi queue.
- Dijaga: bila kebutuhan async meningkat, tinjau ulang lewat ADR baru (Redis/Horizon).
