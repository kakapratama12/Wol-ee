# Deployment Brief — Wol-ee v0.2.0

**Tanggal:** 30 Juni 2026
**Branch:** `main` → `075449b`
**Dari:** v0.1.0 (16 Juni 2026)

---

## Ringkasan

40 commits sejak v0.1.0. Fokus: Invoice Line Items, Production Batch, Brand Refresh, Dashboard Filter, Report Cleanup.

---

## 1. Migrations (WAJIB jalan)

5 migration baru, urutan penting:

```bash
php artisan migrate
```

| # | File | Deskripsi | Impact |
|---|------|-----------|--------|
| 1 | `alter_products_selling_price_nullable` | `products.selling_price` jadi nullable | Kolom diubah, data existing aman |
| 2 | `create_invoice_fees_table` | Tabel baru `invoice_fees` | CREATE TABLE |
| 3 | `increase_invoice_decimal_precision` | `invoice_items` + `invoices` decimal → 18,4 | ALTER COLUMN (PostgreSQL) |
| 4 | `add_occurred_at_to_expenses` | Tambah `occurred_at` ke `expenses` + data migration | ALTER + UPDATE existing rows |
| 5 | `add_user_id_to_stock_movements` | Tambah `user_id` ke `stock_movements` | ALTER + ADD FK |

**Catatan:**
- Migration #4 otomatis migrate data existing (set `occurred_at` dari `period_month`/`period_year`)
- Migration #3 pakai raw SQL (`ALTER COLUMN ... TYPE`) — PostgreSQL specific
- Tidak ada rollback concern, semua additive

---

## 2. Asset Build

```bash
npm run build
```

Vite build menghasilkan:
- CSS: `app-*.css` (51KB) — brand colors baru + typography Figtree
- JS: semua pages + components

---

## 3. File Public Baru

| File | Size | Keterangan |
|------|------|------------|
| `public/logo.png` | 287KB | Brand logo (sidebar + login) |
| `public/favicon.ico` | 1KB | Favicon utama |
| `public/favicon-16x16.png` | 1KB | Favicon kecil |
| `public/favicon-32x32.png` | 2KB | Favicon standar |
| `public/apple-touch-icon.png` | 29KB | iPhone homescreen icon |

---

## 4. Konfigurasi Baru

### Brand Colors (CSS Variables)

```css
--primary: 218 47% 20%;     /* Deep Navy #1A2B49 */
--success: 174 70% 53%;     /* Teal #2DD4BF */
--warning: 38 91% 55%;      /* Warm Amber #F5A623 */
--background: 60 20% 98%;   /* Off White #FAFAF9 */
--foreground: 217 25% 27%;  /* Charcoal #374151 */
--muted-foreground: 215 20% 65%; /* Slate #94A3B8 */
--border: 220 13% 95%;      /* Light Gray #F3F4F6 */
```

### Tailwind Config

`fontFamily.sans` sekarang Figtree (bukan Inter). Tidak ada perubahan dependencies.

---

## 5. Fitur Baru (User-Facing)

### Dashboard
- Date filter: Minggu Ini, Bulan Ini, 3 Bulan, Custom
- Metrics ikut filter tanggal

### P&L Report
- Hapus quantity (pcs) dari breakdown
- COGS & Expenses: angka positif (bukan kurung)
- Hanya Laba Bersih yang pakai warna (hijau/merah)

### Cashflow Report
- Kas Keluar: angka positif (bukan kurung)
- Hanya Saldo Akhir yang pakai warna

### Invoice
- Extra charges / delivery fee (biaya tambahan)
- Line items support
- PDF + kuitansi

### Production
- Batch production runs
- Yield editing per production run
- Multi-level BOM (Prep items)

### Branding
- Logo sidebar + login dari brand image
- Favicon transparan dari icon mark
- Deep Navy primary color scheme

---

## 6. API Changes

Tidak ada breaking change di API bot. Endpoint baru:
- `GET /api/bot/usage` — cek kuota AI
- `POST /api/bot/ai-usage` — log usage

---

## 7. Rollback Plan

Jika ada masalah:
1. `git checkout v0.1.0` (tag sebelumnya)
2. `npm run build`
3. Rollback migrations:
   ```bash
   php artisan migrate:rollback --step=5
   ```

---

## 8. Checklist Deploy

- [ ] `git pull origin main`
- [ ] `composer install --no-dev`
- [ ] `npm run build`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:clear && php artisan view:cache`
- [ ] Restart queue worker (jika ada): `php artisan queue:restart`
- [ ] Verify: login, dashboard, P&L, Cashflow, Invoice

---

## 9. Known Issues

- Tidak ada known issues
- Semua test passing di develop branch
