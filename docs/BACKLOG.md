# Wol-ee Backlog

> Terakhir diperbarui: 30 Juni 2026

---

## WIP (What We're Building Now)

### Edit Identitas Usaha (30 Juni 2026)
- [ ] Edit nama usaha + slug di Company Settings
- [ ] Auto-generate slug dari nama baru (bisa di-override)
- [ ] Slug uniqueness check (append -2, -3 jika clash)
- [ ] Audit log perubahan nama
- [ ] URL preview saat edit slug

---

## Icebox (Long-term, no priority yet)

### Phase 3 PRD Features
- Receipt printing
- Email parsing (auto-import transaksi dari email bank)
- B2B Orders (input order B2B, hitung bahan)
- Receivables Tracking ("Siapa yang masih hutang?")
- Invoice Generation ("Bikin invoice untuk Mariot")
- Invoice Management (buat, kirim, track invoice)
- Export Reports (PDF export)

---

## Done / Finished

### Code Quality & Infrastructure (Juni 2026)
- Race condition fix: lockForUpdate() di InventoryService & ProductionRunService
- Idempotency key: sales, transactions, invoices (UUID, mencegah double submit)
- Error handling: try-catch di CashEntry, Expense, Ingredient controllers
- ESLint + Prettier: frontend code style guardrails
- Dark mode: toggle button, localStorage persistence, logo auto-switch

### Super Admin Platform (Juni 2026)
- Platform Overview
- User Management (CRUD + role + search + filter by usaha)
- Usaha Management (list + search + expandable pengelola details)
- Feedback Inbox
- AI Usage Analytics
- Bot Skills Registry

### Terminologi Update (Juni 2026)
- Tenant → Usaha
- Owner → Pengelola
- Admin → Staff

### Dashboard Features
- Dashboard date filter (Minggu Ini, Bulan Ini, 3 Bulan, Custom)
- PnL collapsible breakdowns (revenue/COGS per product)
- Cashflow report
- Aging Report
- Tax Simulator
- Margin Protection

### Inventory & Production
- Inventory management (bahan baku, prep, produk jadi)
- Recipe management + gramasi
- Production run (catat produksi, yield, waste)
- COGS per-period (average dari production runs)
- Weighted average price untuk bahan baku

### Transactions
- Pembelian + edit + hapus
- Penjualan + edit + hapus
- Biaya operasional + backdate support
- Audit trail (user_id di stock_movements)

### Partner & Invoice
- Partner management (customer & supplier)
- Invoice tracking (create, pay, outstanding)
- Aging summary

### Bot Telegram
- NL input (jual, beli, stok)
- Stock check
- Quick report (profit bulan ini)
- Stock alerts
- Confirmation flow
- Batch entry
- AI quota management

### Brand & Design
- Deep Navy #1A2B49 primary
- Warm Amber #F5A623 warning
- Teal #2DD4BF success
- Figtree typography (everywhere)
- Favicon + logo (sidebar + login)

### Bugs Fixed (9 items, Juni 2026)
- Scroll issue di edit resep
- Error input bahan baru
- Extra charges di invoice (delivery fee)
- Unit of measure dengan harga beda (pcs/box)
- Backdate input
- COGS calculator
- Quick-add box
- Cost categories
