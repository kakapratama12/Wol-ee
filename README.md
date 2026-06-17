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
| POST   | `/api/expenses`       | Catat biaya operasional (`category`, `amount`, `period_month`, `period_year`) |
| GET    | `/api/stock`          | Daftar stok + status (aman/menipis/kritis) |
| GET    | `/api/reports/today`  | Ringkasan omset/profit hari ini |
| GET    | `/api/reports/pnl`    | P&L bulanan (`?month=&year=`) |
| GET    | `/api/reports/stock-alerts` | Bahan menipis/kritis saja |
| GET    | `/api/reports/margin-alerts` | Produk dengan margin turun |
| GET    | `/api/reports/top-products` | Produk paling laku (`?month=&year=&limit=`) |
| GET    | `/api/reports/bottom-products` | Produk paling sepi (`?month=&year=&limit=`) |
| GET    | `/api/bot/usage`      | Sisa kuota AI harian (`?telegram_user_id=`) |
| POST   | `/api/bot/ai-usage`   | Konsumsi 1 kuota AI (body: `telegram_user_id`) |
| POST   | `/api/bot/ai-requests` | Catat event request LLM untuk analytics provider/tenant |
| POST   | `/api/bot/feedback`   | Catat feedback early adopter untuk kurasi roadmap |

`quantity` dikirim dalam *base unit* bahan (mis. gram, ml). Rate limit 60 req/menit per tenant.

**Kuota AI bot:** plan `free` = 25 panggilan LLM/hari (Groq); `pro`/`business` = 150/hari (DeepSeek). Limit produk dan limit provider (`req/min`, `req/hari`) bisa diatur via `AI_PLAN_*` dan `AI_PROVIDER_*` env, lalu dipantau di `/platform/ai-usage`. Query laporan tanpa LLM tidak mengurangi kuota. Lihat [docs/bot-query-tools-spec.md](docs/bot-query-tools-spec.md).

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

### Queue worker

Efek samping non-kritikal (mis. peringatan stok ke Telegram) dijalankan via queue
(driver `database`, lihat [ADR-0005](docs/adr/0005-async-queue-for-side-effects.md)).

- **Dev**: `./vendor/bin/sail artisan queue:work`
- **Produksi**: jalankan worker sebagai service Supervisor. Contoh config ada di
  [`docs/deploy/supervisor-wolee-worker.conf`](docs/deploy/supervisor-wolee-worker.conf).
  Setelah deploy ulang, restart worker: `php artisan queue:restart`.

Set `WOLEE_TELEGRAM_BOT_TOKEN` & `WOLEE_TELEGRAM_ALERT_CHAT_ID` di `.env` untuk
mengaktifkan notifikasi; bila kosong, peringatan hanya ditulis ke log.
