# Wol-ee Sprint Plan

> **Source of truth** — Update status setelah selesai development.
> Status: `[ ]` = belum, `[x]` = done, `[-]` = skip/ditunda

---

## ⚠️ Git Workflow (IMPORTANT)

```
main     → production (hanya dari develop via PR/merge)
develop  → current development (cursor push ke sini)
feature/ → optional, untuk feature besar
```

**Rules:**
1. **Cursor push ke `develop`**, BUKAN ke `main`
2. **Main hanya diupdate** via PR dari develop (atau merge manual setelah review)
3. **Commit message format:** `[Sprint X] Description` (contoh: `[Sprint 1] Add tenant_id to ingredients`)
4. **Setiap selesai task**, update status di `task.md` (centang `[x]`)

---

## Sprint 1: Foundation

### 1.1 Multi-Tenant Isolation
- [ ] Buat `tenants` table (id, name, slug, plan, status, created_at)
- [ ] Tambah `tenant_id` ke: ingredients, products, transactions, sales, expenses, suppliers, recipe_items, stock_movements, price_histories
- [ ] Tambah `tenant_id` ke: users (user belongs to tenant)
- [ ] Update semua model: tambah relation ke Tenant
- [ ] Update semua controller: filter by auth()->user()->tenant_id
- [ ] Database seeder: create sample tenant

### 1.2 Auth & Roles
- [ ] Login/register endpoint (Sanctum)
- [ ] Role: owner (full access), admin (limited)
- [ ] Middleware: check role
- [ ] Dashboard auth (session-based)

### 1.3 Database Cleanup
- [ ] Pastikan semua migration jalan
- [ ] Pastikan semua relation benar
- [ ] Pastikan factory & seeder works

**Sprint 1 Done Criteria:**
- ✅ User bisa login
- ✅ Data terisolasi per tenant
- ✅ Role owner/admin works

---

## Sprint 2: Partner & Invoice

### 2.1 Partner Management
- [ ] Partners table (id, tenant_id, name, type[customer/supplier], contact, phone, email, address)
- [ ] Partner model + migration
- [ ] PartnerController: CRUD
- [ ] Dashboard: Partner list page
- [ ] Dashboard: Partner detail page (history transaksi)

### 2.2 Partner Aging
- [ ] Aging calculation logic (0-30, 31-60, 61-90, 90+ days)
- [ ] Dashboard: Aging report page
- [ ] Bot endpoint: GET /api/partners/aging

### 2.3 Invoice Tracking
- [ ] Invoices table (id, tenant_id, partner_id, amount, due_date, status[paid/outstanding], paid_at)
- [ ] Invoice model + migration
- [ ] InvoiceController: CRUD
- [ ] Dashboard: Invoice list page
- [ ] Dashboard: Mark invoice as paid

**Sprint 2 Done Criteria:**
- ✅ Bisa CRUD partner
- ✅ Aging report tampil di dashboard
- ✅ Bisa create & track invoice

---

## Sprint 3: Dashboard Enhancement

### 3.1 Dashboard Overview
- [ ] Summary cards: Omset, COGS, Profit, Margin
- [ ] Chart: Revenue vs Expense (bulanan)
- [ ] Recent transactions list

### 3.2 P&L Report
- [ ] P&L calculation (Revenue - COGS - Expenses)
- [ ] Dashboard: P&L page
- [ ] Export to Excel

### 3.3 Tax Simulator
- [ ] Tax simulator page (input: omset, COGS, expense, waste%, business type)
- [ ] Output: PP 23 vs Normal comparison
- [ ] Business type selector (perorangan/CV/PT)

### 3.4 Margin Protection
- [ ] Price tracker (historical prices)
- [ ] Margin alerts (margin turun > 2%)
- [ ] What-if simulator (kalau harga naik X%, margin jadi berapa)

**Sprint 3 Done Criteria:**
- ✅ Dashboard overview lengkap
- ✅ P&L bisa di-export
- ✅ Tax simulator works
- ✅ Margin alerts muncul

---

## Sprint 4: Bot Integration

> **Integration Spec:** Bot (Python) → HTTP POST → Laravel API → DB Wol-ee

### 4.1 Laravel API (sudah ada, perlu review)
- [ ] POST /api/transactions (pembelian) ✓
- [ ] POST /api/sales (penjualan) ✓
- [ ] GET /api/stock (cek stok) ✓
- [ ] GET /api/partners/aging (cek aging) —待Sprint 2
- [ ] Auth: API token per tenant

### 4.2 Bot Changes (Python)
- [ ] Tambah config: `WOL_EE_API_URL` & `WOL_EE_API_TOKEN`
- [ ] Tambah function: `post_to_wol_ee(endpoint, data)`
- [ ] Update transaction handler: setelah parse NL → call Laravel API
- [ ] Update sales handler: setelah parse NL → call Laravel API
- [ ] Update stock check: call Laravel API (atau query langsung)
- [ ] Fallback: kalau API down → simpan ke local DB, retry nanti
- [ ] Logging: log semua API calls (success/failure)

### 4.3 Integration Flow
```
User: "Beli tepung 2kg harga 18rb"
    ↓
Bot: Parse NL → {item: "tepung", qty: 2, unit: "kg", price: 18000}
    ↓
Bot: POST /api/transactions {ingredient: "tepung", quantity: 2, unit_price: 18000, source: "bot"}
    ↓
Laravel: Validate → Save ke DB → Update stock
    ↓
Laravel: Return {message: "Tercatat", new_stock: 5.5, stock_status: "aman"}
    ↓
Bot: Reply ke user: "✅ Pembelian tercatat. Stok tepung: 5.5 kg (aman)"
```

### 4.4 Response Format (Bot → User)
```json
// Success
{
  "success": true,
  "message": "Pembelian tercatat.",
  "data": {
    "ingredient": "tepung",
    "quantity": 2,
    "unit": "kg",
    "total": 36000,
    "new_stock": 5.5,
    "stock_status": "aman"
  }
}

// Incomplete data
{
  "success": false,
  "message": "Harga belum diisi. Berapa per kg?",
  "missing": ["unit_price"]
}
```

### 4.5 Error Handling
- API timeout → retry 1x → fallback ke local DB
- API error 422 → return validation error ke user
- API error 500 → log error, return generic message

**Sprint 4 Done Criteria:**
- ✅ Bot bisa input transaksi → masuk ke DB Wol-ee
- ✅ Bot bisa cek stok → data dari DB Wol-ee
- ✅ Data sync bot ↔ dashboard real-time
- ✅ Fallback works kalau API down

---

## Sprint 5: Super Admin (Phase 2)

### 5.1 Tenant Management
- [ ] Super admin panel
- [ ] List semua tenant
- [ ] Create/suspend/delete tenant

### 5.2 Billing
- [ ] Subscription management
- [ ] Payment tracking

### 5.3 Monitoring
- [ ] Active users stats
- [ ] Bot usage stats
- [ ] Error logs

**Sprint 5 Done Criteria:**
- ✅ Super admin bisa manage tenant
- ✅ Billing visible

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
- **Bot integration** — bisa delay, dashboard lebih prioritas
- **Super admin** — Phase 2, gak urgent

---

*Last updated: 19 June 2026*
*Owner: Odi (kakapratama12)*
