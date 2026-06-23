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
- [x] PartnerController: CRUD (API + web)
- [x] Dashboard: Partner list page
- [x] Dashboard: Partner detail page (aging + outstanding invoices)

### 2.2 Partner Aging
- [x] Aging calculation logic (0-30, 31-60, 61-90, 90+ days)
- [x] Dashboard: Aging report page (di partner detail)
- [x] Bot endpoint: GET /api/reports/aging (+ GET /api/partners/{id}/aging)

### 2.3 Invoice Tracking
- [x] Invoices table (id, tenant_id, partner_id, amount, due_date, status[paid/outstanding/partial], paid_at)
- [x] Invoice model + migration
- [x] InvoiceController: CRUD (API + web)
- [x] Dashboard: Invoice list page
- [x] Dashboard: Mark invoice as paid (dashboard UI)

**Sprint 2 Done Criteria:**
- [x] Bisa CRUD partner (API + dashboard)
- [x] Aging report tampil di dashboard
- [x] Bisa create & track invoice (API + dashboard)

---

## Sprint 3: Dashboard Enhancement — [x] DONE (MVP v0.1.0)

> Sudah live di production. Item di bawah = baseline selesai. Lihat **Gaps** untuk enhancement berikutnya.

### 3.1 Dashboard Overview
- [x] Summary cards: Omset, COGS, Profit, Margin
- [x] Chart: Revenue vs Expense (bulanan)
- [x] Recent sales list
- [x] Recent purchase transactions list

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

**Sprint 3 Done Criteria:** ✅

---

## Sprint 4: Bot Integration

### 4.1 API untuk Bot
- [x] POST /api/transactions (pembelian)
- [x] POST /api/sales (penjualan)
- [x] GET /api/stock (cek stok)
- [x] GET /api/reports/today
- [x] GET /api/reports/aging (cek aging)
- [x] Auth: **1 API token per tenant** (`{tenant_id}:{secret}`, hashed di `tenants.bot_token`)
  - Middleware `BotTokenAuth` + `POST /api/bot/validate-token`
  - Generate via Settings > Bot Integration atau `php artisan wol-ee:generate-bot-token`
- [x] Error format standar (`success`, `message`, `error_code`, `errors`/`suggestions`)
- [x] Idempotency: **tidak perlu v1** (single user input, no race condition)

### 4.2 Bot Logic
- [x] NL parsing rules — modul `bot/handlers.py`
- [x] AI action planner intent + slot validation untuk sale/purchase/expense
- [x] Incomplete data handling (format hint + offline queue saat timeout)
- [x] Response format (JSON via `bot/wol_ee_client.py`)

### 4.3 Bot → Dashboard Sync
- [x] Data dari bot muncul di dashboard (setelah tenant scoping)
- [x] Source tagging (bot vs dashboard via `source` column)
- [x] Edit data bot via dashboard (penjualan & pembelian bot bisa dikoreksi/hapus via dashboard)
- [x] Bot bisa mencatat biaya operasional via `POST /api/expenses`

### 4.4 Bot Query Tools (demo owner) — [x] DONE
- [x] `GET /api/reports/pnl`, `/margin-alerts`, `/stock-alerts`
- [x] Query router bot (NL laporan tanpa LLM): profit bulan ini, omset hari ini, stok/margin alert
- [x] `explain_capabilities` — meta "bisa nanya apa"
- [x] Fix `/summary` → PnL bulan ini (bukan hari ini)
- Spec: [docs/bot-query-tools-spec.md](docs/bot-query-tools-spec.md)

### 4.5 Bot AI Quota — [x] DONE
- [x] Tabel `bot_ai_usages` + `BotUsageService`
- [x] `GET /api/bot/usage`, `POST /api/bot/ai-usage` (consume)
- [x] Free: 25 AI/hari (Groq); Pro/Business: 150/hari (DeepSeek)
- [x] Tier LLM dari `tenants.plan`, bukan legacy keuangan-bot `User.plan`

**Sprint 4 Done Criteria:**
- [x] Bot auth per-tenant token
- [x] Bot bisa input transaksi & penjualan (API ready)
- [x] Bot bisa cek stok
- [x] Data sync bot ↔ dashboard (modul `bot/` deployed ke keuangan-bot, NL via ai_parser)

---

## Sprint 5: Super Admin (Phase 2) — [-] SKIP sampai spec dikonfirmasi

> Spec draft di bawah — **jangan implement** sampai tim approve.

### 5A. Super Admin Ops Panel — [x] DONE
- [x] Role `super_admin` + middleware akses platform
- [x] Bootstrap super admin via `php artisan wol-ee:create-super-admin`
- [x] `/platform` overview operasional
- [x] `/platform/tenants` tenant overview
- [x] `/platform/feedback` feedback inbox + update status/note
- [x] `/platform/ai-usage` usage summary + provider/request analytics dari `bot_ai_usages` dan `bot_ai_requests`
- [x] `/platform/bot-skills` read-only static skill registry

### 5.1 Tenant Management
- [x] Role: `super_admin` (terpisah dari owner/admin)
- [ ] Login: panel terpisah di `/platform/login`
- [x] List semua tenant
- [ ] Create / suspend / soft-delete tenant (`status`: active | suspended | deleted; data retained)

### 5.2 Billing
- [ ] Billing MVP **manual** — `tenants.plan` = `free` | `pro` | `business`
- [ ] Tanpa payment gateway di v1

### 5.3 Monitoring
- [ ] Laravel activity log
- [ ] Bot API call count (`api_calls` table)
- [x] AI usage analytics basic: quota consumption per tenant dan aggregate
- [x] Filter monitoring basic per tenant/plan/periode (`bot_ai_usages`)
- [x] Feedback inbox: review `bot_feedbacks`, status `new/reviewed/planned/shipped/rejected`
- [x] Token/LLM usage detail, provider metrics, dan request per minute (`bot_ai_requests`)
- [ ] Feedback tag/kategori kebutuhan
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

## Sprint 6: Batch Model & Production Run

> PRD v0.5 — Two-layer inventory, production run, waste tracking.

### 6.1 Database Schema

- [x] Add `recipe_type` field to `products` table (enum: `unit`, `batch`)
- [x] Add `item_type` field to `ingredients` table (enum: `raw_material`, `finished_goods`)
- [x] Create `production_runs` table:
  - `id`, `tenant_id`, `recipe_id` (FK products), `batch_count`
  - `yield_actual` (integer), `waste_count` (integer)
  - `total_cost` (decimal, snapshot)
  - `notes`, `produced_at`, `timestamps`
- [x] Create `production_run_items` table (pivot):
  - `id`, `production_run_id`, `ingredient_id`
  - `quantity_used` (decimal), `unit_cost_snapshot` (decimal)
- [x] Update `stock_movements` types: add `production_input`, `production_output`, `waste`
- [x] Add `production_run_id` nullable FK to `stock_movements` (traceability)

### 6.2 Models & Relations

- [x] Update `Product` model: add `recipe_type` to fillable/casts
- [x] Update `Ingredient` model: add `item_type` to fillable/casts
- [x] Create `ProductionRun` model + relations (belongsTo Product, hasMany ProductionRunItems, hasMany StockMovements)
- [x] Create `ProductionRunItem` model + relations
- [x] Update `StockMovement` model: add new type constants

### 6.3 Production Run Service

- [x] `ProductionRunService::create($data)` — main orchestrator:
  - Validate bahan cukup (hard validation)
  - Snapshot harga bahan saat produksi
  - Create production_run record
  - Create production_run_items
  - Deduct raw materials from stock (stock_movements: production_input)
  - Add finished goods to stock (stock_movements: production_output)
  - Record waste if any (stock_movements: waste)
- [x] `ProductionRunService::reverse($id)` — pembatalan produksi
- [x] Warning logic: yield deviation > 20% dari resep (soft warning, di service)

### 6.4 API Endpoints

- [x] `POST /api/production-runs` — create production run
- [x] `GET /api/production-runs` — list production runs (filterable by date, recipe)
- [x] `GET /api/production-runs/{id}` — detail production run
- [x] `DELETE /api/production-runs/{id}` — reverse production run

### 6.5 Dashboard UI

- [x] Production Run list page (table: date, recipe, batch, yield, cost, waste)
- [x] Production Run create page:
  - Select recipe (auto-fill bahan dari resep)
  - Input batch count
  - Edit bahan terpakai (editable fields)
  - Input yield aktual + waste
  - Submit
- [x] Update P&L report: tambah baris "Waste Expense" terpisah dari COGS

### 6.6 Seeders & Testing

- [ ] Seeder: tambah sample batch recipe (Croissant) + raw materials
- [ ] Seeder: tambah sample finished goods (Croissant as finished_goods ingredient)
- [ ] Test: production run deducts raw materials correctly
- [ ] Test: production run adds finished goods correctly
- [ ] Test: waste recorded as separate expense
- [ ] Test: cost snapshot is historical (doesn't change with current price)

**Sprint 6 Done Criteria:**
- [ ] User bisa catat production run via dashboard
- [ ] Stok bahan baku otomatis berkurang
- [ ] Stok produk jadi otomatis bertambah
- [ ] Waste tercatat terpisah di P&L
- [ ] Cost snapshot stabil (historical COGS ga berubah)

---

## Sprint 7: Multi-Level BOM, Prep & Financial Reports

### 7.1 Production Run Simplification
- [x] Production run creation: auto-use recipe quantities (no manual ingredient input)
- [x] Edit bahan button in production history (adjust actual quantities post-production)
- [x] updateItems() handles stock diff + cost recalculation

### 7.2 Prep Item Type
- [x] Ingredient model: `is_prep` flag on products
- [x] ProductionRunService: prep products create prep ingredients (`item_type=prep`)
- [x] FinishedGoodsController: exclude prep products from finished goods page
- [x] PrepStockController: dedicated page for prep items (card layout, stock movements)
- [x] Sidebar: 3 inventory sections (Stok Bahan Dasar, Stok Prep, Stok Produk Jadi)
- [x] Products page: Kategori dropdown (Produk Jadi / Prep) for batch products

### 7.3 Recipe Restrictions
- [x] Prep recipes: only allow raw_material ingredients
- [x] Produk Jadi recipes: allow raw_material + prep ingredients
- [x] Server-side + frontend validation

### 7.4 Expense Categories
- [x] Migration: add category column to expenses (bahan_baku, operasional, overhead, non_operasional)
- [x] "Di Luar Usaha" category for cicilan, prive, beli aset
- [x] P&L excludes non_operasional expenses
- [x] Badge colors per category

### 7.5 Cashflow Report
- [x] CashEntry model + migration for modal/capital inflows
- [x] CashflowService: derives cashflow from Sales, Transactions, Expenses, CashEntries
- [x] Cashflow page with month/year picker, saldo display
- [x] "Catat Kas Masuk" form for modal awal/tambahan
- [x] Sidebar: "Laporan Cashflow" under Laporan group
- [x] Saldo carry-forward otomatis

### 7.6 UI Cleanup
- [x] Sidebar: split Inventory (3 stock pages) and Produk (Resep + Produksi) groups
- [x] Inventory page: removed tabs, only shows raw_material items
- [x] Mobile-friendly card layout for production run ingredients

---

## Notes

- **Tenant ID is CRITICAL** — harus di Sprint 1, jangan ditunda
- **Partner & Invoice** — penting untuk B2B use case
- **Bot integration** — API dasar ada; tenant token + wire Python bot = sisa kerja
- **Super admin** — Phase 2, spec di atas menunggu konfirmasi tim

---

*Last updated: 19 June 2026*
*Owner: Odi (kakapratama12)*

---

## Sprint 8: UX Improvement — Currency, PDF Invoice, Inline Partner, Resep Scroll

### 8.1 Currency Input Component (Global)
- [x] Buat reusable CurrencyInput component (auto-format ribuan)
- [x] Apply ke: invoice, expense, pembelian, penjualan
- [x] Backend terima raw number

### 8.2 PDF Invoice
- [x] Install DomPDF
- [x] Template: header usaha, data partner, line items, total, jatuh tempo
- [x] Route: GET /invoices/{id}/pdf
- [x] Button "Download PDF" di invoice detail

### 8.3 Inline Partner Creation
- [x] Buat CreatableCombobox component (reusable)
- [x] Apply ke form invoice (partner dropdown)
- [ ] Minimal field: nama + type (auto-set customer)

### 8.4 Bahan Baku — Volume + Harga Auto-Calc
- [ ] Form tambah bahan: ganti unit_price jadi input volume + satuan + harga_total
- [ ] Auto-calculate: unit_price = harga_total / volume
- [ ] Backend: IngredientController store handle kalkulasi

### 8.5 Modal Resep — Scroll Fix
- [ ] Tambah max-h + overflow-y-auto ke modal tambah bahan di resep
- [x] Test: list bahan banyak, scroll gak tembus layar

**Sprint 8 Done Criteria:**
- [x] Input currency otomatis format ribuan di semua form keuangan
- [x] Invoice bisa di-download sebagai PDF
- [x] Partner bisa dibuat langsung dari form invoice
- [x] Harga bahan baku dihitung otomatis dari volume + harga
- [x] Modal resep gak tembus layar

*Last updated: 23 June 2026*