# Security

Baseline keamanan Wol-ee. Lihat juga `AGENTS.md` §8.6.

## Prinsip

- **Secrets tidak pernah di-commit.** `.env` di-ignore; `.env.example` hanya berisi
  placeholder. Rotasi `APP_KEY`/token bila bocor.
- **Validasi & otorisasi.** Semua input lewat Form Request. Akses halaman/aksi
  sensitif (owner-only) dijaga middleware `owner` (`EnsureUserIsOwner`).
- **API bot.** `auth:sanctum` + rate limit (`throttle:bot`). Bot tidak pernah
  query DB langsung. Token dibuat via `php artisan wolee:bot-token` dan bisa
  dicabut per token.
- **Uang & data.** Perhitungan di backend, atomic (`DB::transaction()`), stok
  sebagai ledger yang auditable.
- **Security headers.** `SecurityHeaders` middleware mengirim `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, dan `Strict-Transport-Security`
  (HTTPS). Pastikan produksi memakai HTTPS.

## Checklist sebelum rilis

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` di-set.
- [ ] HTTPS aktif (HSTS akan otomatis terkirim).
- [ ] `composer audit` & `npm audit` bersih (atau risiko dipahami).
- [ ] Tidak ada secret di repo / log.
- [ ] Migrasi terbaru dijalankan (`migrate --force`); tidak ada perubahan DB manual.
- [ ] Token bot diset di server produksi, bukan di-hardcode.
- [ ] Backup database terjadwal.

## Audit dependency

Jalankan saat menambah/menaikkan paket dan minimal sebelum rilis:

```bash
composer audit
npm audit
```

## Melaporkan kerentanan

Laporkan secara privat ke maintainer repo (jangan buka issue publik untuk
kerentanan yang belum diperbaiki).
