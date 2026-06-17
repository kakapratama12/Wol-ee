# ADR-0004: Integrasi bot lewat API ber-Sanctum (bukan akses DB langsung)

- Status: Accepted
- Tanggal: 2026-06-16
- Pengambil keputusan: tim Wol-ee

## Konteks

Bot Telegram (Python, repo terpisah) perlu mencatat transaksi/penjualan dan
membaca stok serta laporan. Di produksi bot dan dashboard berbagi satu database
Postgres. Pertanyaannya: apakah bot query DB langsung atau lewat API?

## Keputusan

Bot **tidak** mengakses database langsung. Bot memanggil **HTTP API** Laravel
yang dilindungi **Sanctum personal access token** + rate limit (`throttle:bot`).
Token dibuat via `php artisan wolee:bot-token`. Logika dijalankan service yang
sama dengan web.

## Alternatif yang dipertimbangkan

- **Bot query DB langsung** — ditolak: melewati validasi & logika bisnis,
  menduplikasi aturan (mutasi stok, snapshot COGS) di Python, rawan inkonsistensi.
- **Message queue antar layanan** — overkill untuk skala saat ini; bisa ditinjau
  ulang bila kebutuhan async lintas-layanan muncul.

## Konsekuensi

- Positif: satu jalur logika & validasi, batas keamanan jelas, mudah di-rate-limit
  dan diaudit per token.
- Trade-off: ada latensi HTTP; API harus dijaga kompatibilitasnya untuk bot.
