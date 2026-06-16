# Wol-ee — Web Dashboard

AI Business Assistant untuk UMKM F&B. Repo ini berisi **web dashboard** (Laravel + Inertia + React) dan **API** yang dipakai bot Telegram. Lihat konteks produk lengkap di [docs/PRD-Wol-ee.md](docs/PRD-Wol-ee.md) dan panduan kontribusi di [AGENTS.md](AGENTS.md).

## Tech Stack

- Laravel 13 (PHP 8.5) + Inertia.js + React (TypeScript)
- Tailwind CSS + komponen shadcn-style
- PostgreSQL
- Auth: Breeze (web session) + Sanctum (token API untuk bot)
- Export Excel: phpoffice/phpspreadsheet
- Dev: Laravel Sail (Docker), Test: Pest

## Menjalankan (Development)

Butuh Docker. Semua perintah lewat Sail.

```bash
cp .env.example .env          # lalu sesuaikan bila perlu
./vendor/bin/sail up -d       # start app + postgres
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
npm install
npm run dev                   # vite dev server
```

Buka http://localhost.

### Akun seed

| Role  | Email                 | Password |
|-------|-----------------------|----------|
| Owner | owner@wol-ee.local    | password |
| Admin | admin@wol-ee.local    | password |

- **Owner**: akses penuh (resep, tax simulator, P&L, margin, biaya).
- **Admin/Staff**: inventory (lihat), input pembelian & penjualan.

## Testing

```bash
./vendor/bin/sail test
```

Test memakai SQLite in-memory (lihat `phpunit.xml`). Logika kritis (`CogsService`, `TaxSimulatorService`, alur penjualan, dan API bot) tercakup.

## Integrasi Bot Telegram

Bot Python memanggil API dengan token Sanctum. Generate token:

```bash
./vendor/bin/sail artisan wolee:bot-token
```

Sertakan header `Authorization: Bearer <token>` pada tiap request.

| Method | Endpoint              | Fungsi |
|--------|-----------------------|--------|
| POST   | `/api/transactions`   | Catat pembelian bahan (`ingredient`/`ingredient_id`, `quantity`, `total` atau `unit_price`) |
| POST   | `/api/sales`          | Catat penjualan (`product`/`product_id`, `quantity`) -> COGS, profit, alert stok |
| GET    | `/api/stock`          | Daftar stok + status (aman/menipis/kritis) |
| GET    | `/api/reports/today`  | Ringkasan omset/profit hari ini |

`quantity` dikirim dalam *base unit* bahan (mis. gram, ml). Rate limit 60 req/menit.

Contoh:

```bash
curl -X POST http://localhost/api/sales \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -d "product=Matcha Latte&quantity=10"
```

## Deploy (Production, VPS)

Target: VPS dengan Nginx + PHP-FPM 8.5 + PostgreSQL.

```bash
git clone <repo> /var/www/wol-ee && cd /var/www/wol-ee
composer install --no-dev --optimize-autoloader
cp .env.example .env   # set APP_ENV=production, APP_KEY, DB_*, APP_URL
php artisan key:generate
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Arahkan root web server ke `public/`.
- Pastikan `storage/` & `bootstrap/cache/` writable.
- DB di-share dengan bot Telegram; bot menulis lewat API (bukan query langsung).
- Generate token bot di server produksi dan simpan di konfigurasi bot.
