# Wol-ee Sprint Plan

> **Source of truth** — branch `develop` di GitHub. Update status setelah selesai development.
> Status: `[ ]` = belum, `[x]` = done, `[-]` = skip/ditunda

---

## Sprint 1: Foundation

### 1.1 Multi-Tenant Isolation
- [x] Buat `tenants` table (id, name, slug, plan, status, created_at)
- [x] Tambah `tenant_id` ke: ingredients, products, transactions, sales, expenses, suppliers, recipe_items, stock_movements, price_histories
- [x] Tambah `tenant_id` ke: users (user belongs to tenant)
- [x] Update semua model: tambah relation ke Tenant (`BelongsToTenant` trait + global scope)
- [x] Update semua controller: filter by auth()->user()->tenant_id (via global scope)
- [x] Database seeder: create sample tenant

### 1.2 Auth & Roles
- [x] Login/register endpoint (Sanctum) — *API Sanctum live; web register via Breeze*
- [x] Role: owner (full access), admin (limited)
- [x] Middleware: check role (`EnsureUserIsOwner`)
- [x] Dashboard auth (session-based via Breeze)

### 1.3 Database Cleanup
- [x] Pastikan semua migration jalan (termasuk tenant)
- [x] Pastikan semua relation benar
- [x] Pastikan factory & seeder works

**Sprint 1 Done Criteria:**
- [x] User bisa login
- [x] Data terisolasi per tenant
- [x] Role owner/admin works

---

## Sprint 2: Partner & Invoice

### 2.1 Partner Management
- [x] Partners table (id, tenant_id, name, type[customer/supplier], contact, phone, email, address)
- [x] Partner model + migration
- [x] PartnerController: CRUD (API)
- [ ] Dashboard: Partner list page
- [ ] Dashboard: Partner detail page (history transaksi)

### 2.2 Partner Aging
- [x] Aging calculation logic (0-30, 31-60, 61-90, 90+ days)
- [ ] Dashboard: Aging report page
- [x] Bot endpoint: GET /api/reports/aging (+ GET /api/partners/{id}/aging)

### 2.3 Invoice Tracking
- [x] Invoices table (id, tenant_id, partner_id, amount, due_date, status[paid/outstanding/partial], paid_at)
- [x] Invoice model + migration
- [x] InvoiceController: CRUD (API)
- [ ] Dashboard: Invoice list page
- [ ] Dashboard: Mark invoice as paid (dashboard UI)

**Sprint 2 Done Criteria:**
- [x] Bisa CRUD partner (API)
- [ ] Aging report tampil di dashboard
- [x] Bisa create & track invoice (API)

---

## Sprint 3: Dashboard Enhancement — [x] DONE (MVP v0.1.0)

> Sudah live di production. Item di bawah = baseline selesai. Lihat **Gaps** untuk enhancement berikutnya.

### 3.1 Dashboard Overview
- [x] Summary cards: Omset, COGS, Profit, Margin
- [ ] Chart: Revenue vs Expense (bulanan) — **GAP**
- [x] Recent sales list
- [ ] Recent purchase transactions list — **GAP** (hanya penjualan, belum pembelian)

### 3.2 P&L Report
- [x] P&L calculation (Revenue - COGS - Expenses)
- [x] Dashboard: P&L page
- [x] Export to Excel

### 3.3 Tax Simulator
- [x] Tax simulator page (input: omset, COGS, expense, waste%, business type)
- [x] Output: PP 23 vs Normal comparison
- [x] Business type selector (perorangan/CV/PT)

### 3.4 Margin Protection
- [x] Price tracker (historical prices)
- [x] Margin alerts (margin turun > 2%)
- [x] What-if simulator (kalau harga naik X%, margin jadi berapa)

**Sprint 3 Done Criteria:** ✅ (dengan gaps chart & recent purchases di atas)

---

## Sprint 4: Bot Integration

### 4.1 API untuk Bot
- [x] POST /api/transactions (pembelian)
- [x] POST /api/sales (penjualan)
- [x] GET /api/stock (cek stok)
- [x] GET /api/reports/today
- [ ] GET /api/partners/aging (cek aging) — **API ready** (`GET /api/reports/aging`, `GET /api/partners/{id}/aging`)
- [ ] Auth: **1 API token per tenant** (bukan per bot instance)
  - Token disimpan di `tenants.api_token` (hashed)
  - Bot kirim header: `Authorization: Bearer <token>`
  - Resolve tenant dari token, bukan dari user Sanctum
- [ ] Error format standar:
  ```json
  {
    "success": false,
    "message": "Validation failed",
    "errors": {
      "ingredient": ["Bahan tidak ditemukan"]
    }
  }
  ```
- [ ] Idempotency: **tidak perlu v1** (single user input, no race condition)

### 4.2 Bot Logic
- [ ] NL parsing rules (item, qty, unit, price) — di repo bot Python
- [ ] Incomplete data handling (tanya clarification / save draft)
- [ ] Response format (JSON untuk bot consume)

### 4.3 Bot → Dashboard Sync
- [x] Data dari bot muncul di dashboard (setelah tenant scoping)
- [x] Source tagging (bot vs dashboard via `source` column)
- [ ] Edit data bot via dashboard — **partial** (belum semua field)

**Sprint 4 Done Criteria:**
- [ ] Bot auth per-tenant token
- [x] Bot bisa input transaksi & penjualan (API ready)
- [x] Bot bisa cek stok
- [ ] Data sync bot ↔ dashboard (perlu wire bot Python + tenant token)

---

## Sprint 5: Super Admin (Phase 2) — [-] SKIP sampai spec dikonfirmasi

> Spec draft di bawah — **jangan implement** sampai tim approve.

### 5.1 Tenant Management
- [ ] Role: `super_admin` (terpisah dari owner/admin)
- [ ] Login: panel terpisah di `/platform/login`
- [ ] List semua tenant
- [ ] Create / suspend / soft-delete tenant (`status`: active | suspended | deleted; data retained)

### 5.2 Billing
- [ ] Billing MVP **manual** — `tenants.plan` = `free` | `pro` | `business`
- [ ] Tanpa payment gateway di v1

### 5.3 Monitoring
- [ ] Laravel activity log
- [ ] Bot API call count (`api_calls` table)
- [ ] Active users & error logs stats

### 5.4 Support
- [ ] Impersonate: super_admin bisa login sebagai owner tenant mana pun

### 5.5 Bootstrap
- [ ] Super admin pertama via `php artisan db:seed --class=SuperAdminSeeder` atau dedicated artisan command

**Sprint 5 Done Criteria:**
- [ ] Super admin bisa manage tenant
- [ ] Billing visible (manual)
- [ ] Impersonate works

---

## Notes

- **Tenant ID is CRITICAL** — harus di Sprint 1, jangan ditunda
- **Partner & Invoice** — penting untuk B2B use case
- **Bot integration** — API dasar ada; tenant token + wire Python bot = sisa kerja
- **Super admin** — Phase 2, spec di atas menunggu konfirmasi tim

---

*Last updated: 17 June 2026*
*Owner: Odi (kakapratama12)*
