# Deploy Brief — Wol-ee Sprint 8-10 + Company Settings

**Branch:** `main` (commit `2ba9756`)
**Date:** 23 June 2026
**Changes:** Sprint 8-10 features + Company Settings + Invoice PDF redesign

---

## Summary

Fitur baru:
- **Sprint 8:** CurrencyInput reusable, PDF invoice template, CreatableCombobox (inline partner), inventory calculator
- **Sprint 9:** Invoice line items (rincian item), PDF preview modal, kuitansi (proof of payment), invoice edit page
- **Company Settings:** Halaman `/settings/company` untuk data perusahaan + logo upload
- **PDF Redesign:** Header pakai `<table>` layout (bukan flexbox), logo di kiri, "Invoice" + info di kanan

---

## New Migrations (2)

```
2026_06_23_123425_create_invoice_items_table
2026_06_23_130000_add_company_fields_to_tenants_table
```

**`invoice_items`** — tabel baru:
- `invoice_id` (FK ke invoices, cascade delete)
- `description` (string)
- `quantity` (decimal 14,4, default 1)
- `unit_price` (decimal 14,4, default 0)
- `total` (decimal 14,4, default 0)

**`tenants`** — 7 kolom baru (nullable):
- `address`, `phone`, `email`
- `bank_name`, `bank_account`, `bank_account_name`
- `logo` (path ke file logo)

---

## Setup Steps

### 1. Pull main
```bash
cd /var/www/wol-ee
git pull origin main
```

### 2. Install deps (jika ada perubahan composer/npm)
```bash
composer install --no-dev --optimize-autoloader
npm run build
```

### 3. Jalankan migrations
```bash
php artisan migrate --force
```

### 4. Storage link (untuk logo upload)
```bash
php artisan storage:link
```

### 5. Buat direktori logo
```bash
mkdir -p storage/app/public/logos
chown -R ubuntu:www-data storage/app/public/logos
chmod -R 775 storage/app/public/logos
```

### 6. Set permissions
```bash
chown -R ubuntu:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 7. Nginx — client_max_body_size
Logo upload max 2MB, pastikan nginx allow upload minimal 5MB:
```nginx
client_max_body_size 5M;
```
Tambahkan di block `server` atau `location /` di config nginx.

### 8. Clear caches
```bash
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
```

### 9. Restart queue worker
```bash
# Jika pakai supervisor:
sudo supervisorctl restart all

# Atau manual:
php artisan queue:restart
```

### 10. Set domain settings (opsional)
User bisa isi data perusahaan di `/settings/company` setelah deploy. Tidak perlu seeder — kolom nullable.

---

## Environment Variables

Tidak ada env var baru. Yang ada sudah di `.env`:
- `FILESYSTEM_DISK=local` ← pastikan ini (bukan s3/r2)
- `APP_URL` ← pastikan sesuai domain produksi

---

## Notes

- Logo disimpan lokal di `storage/app/public/logos/{tenant_id}/`. Filename selalu `logo.{ext}` (overwrite).
- Invoice line items backward compatible — invoice lama (amount saja) tetap tampil. Invoice baru bisa pakai items DAN/ATAU amount.
- PDF pakai DomPDF + `<table>` layout (bukan flexbox — DomPDF tidak support flex dengan baik).
- CurrencyInput auto-format ribuan di semua form: invoice, expense, transaction, sales, cashflow.
