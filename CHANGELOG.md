# Changelog

Semua perubahan penting pada proyek ini didokumentasikan di sini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
dan proyek ini memakai [Semantic Versioning](https://semver.org/lang/id/).

Kapan entri ditambahkan: lihat kebijakan di
[`AGENTS.md` §8 Engineering Workflow](AGENTS.md#8-engineering-workflow).
Singkatnya — catat perubahan yang **terlihat/berdampak ke pengguna atau operator**
(fitur, perbaikan bug, breaking change, keamanan, deprecation). Perubahan internal
murni (refactor, test, tooling) tidak perlu masuk changelog.

## [Unreleased]

### Added

- Bot query tools (demo owner): `GET /api/reports/pnl`, `/stock-alerts`, `/margin-alerts`; query router NL tanpa LLM (profit bulan ini, stok/margin alert, meta "bisa nanya apa").
- Kuota AI bot: 25/hari (free/Groq), 150/hari (pro/business/DeepSeek); `GET /api/bot/usage`, `POST /api/bot/ai-usage`.
- Sidebar navigasi dikategorikan (Transaksi, Inventory, Laporan, Partner, Settings) dengan section collapsible.
- Halaman web **Aging Report** (`/reports/aging`) — tersembunyi jika tenant belum punya invoice.
- Kolom `weighted_avg_price` pada bahan baku; COGS memakai rata-rata tertimbang, snapshot penjualan tetap akurat.
- Dashboard Partner & Invoice: list, detail, aging summary, dan form pembayaran.
- Sprint 4: auth token per tenant (`{tenant_id}:{secret}`), middleware `BotTokenAuth`,
  endpoint `POST /api/bot/validate-token`, response format standar API bot.
- Dashboard Settings > Bot Integration (generate/copy token).
- Modul Python bot (`bot/`) — API client, handlers NL, offline queue, deployment checklist.
- Bot NL parsing: `ai_parser.py` adaptasi logic keuangan-bot → output Laravel API (beli/jual/stok NL).
- Bot API read endpoints: `GET /api/transactions`, `GET /api/sales`, `GET /api/products`.
- Bot Wol-ee path: `/profit`, `/history`, `/partners` via API untuk user terdaftar; legacy path tetap untuk user lain.
- Bot batch entry: AI intent `sale_batch`/`purchase_batch`, konfirmasi inline keyboard, endpoint `POST /api/sales/batch` dan `POST /api/transactions/batch`.
- Bot item not found: daftar item tersedia + link dashboard (tanpa fuzzy mapping).
- Edit dan hapus penjualan & pembelian di dashboard (koreksi stok otomatis).
- Edit biaya operasional di dashboard.
- Sprint 2 API: Partner CRUD, Invoice tracking (create, pay, outstanding), aging report
  (`/api/partners`, `/api/invoices`, `/api/reports/aging`).
- Seeder sample partner & invoice untuk tenant `kafe-contoh`.
- Workflow rekayasa: kebijakan commit, changelog, dan ADR di `AGENTS.md`.
- `CHANGELOG.md` dan direktori `docs/adr/` (Architecture Decision Records).
- Fondasi queue: event `SaleRecorded` + listener `SendLowStockAlert` (queued)
  yang mengirim peringatan stok menipis/kritis ke Telegram setelah penjualan.
- Middleware `SecurityHeaders` untuk security headers di semua response web.
- `SECURITY.md`: checklist keamanan & prosedur audit dependency.

### Fixed

- Tombol salin token di Settings > Bot Integration (fallback mobile + feedback "Disalin!").

### Changed

- `/summary` dan "ringkasan" di bot → laporan P&L bulan ini (bukan hari ini).
- Tier LLM bot mengikuti `tenants.plan` Wol-ee (bukan legacy `User.plan` keuangan-bot).
- Penghapusan pembelian ditolak jika stok bahan sudah terpakai (pesan error jelas).
- `InventoryService::recordPurchase()` memperbarui weighted average (bukan hanya harga beli terakhir).
- `CogsService` memakai `weighted_avg_price` untuk perhitungan COGS live & snapshot penjualan.
- Dokumentasi produk (`docs/PRD`, `docs/Roles-Sequence-Diagram`, `docs/Development-Context`)
  diperbarui: prioritas fitur (Partner, Aging, Invoice tracking P1), MVP scope
  diselaraskan dengan implementasi v0.1.0, stack Inertia/React, status deploy.

### Security

- Memaksa `shell-quote` ≥ 1.8.4 via `overrides` untuk menutup advisory kritikal
  GHSA-w7jw-789q-3m8p (transitif dari `concurrently`, dev-only).

## [0.1.0] - 2026-06-16

Rilis awal: MVP dashboard Wol-ee + integrasi API ke bot Telegram.

### Added

- Scaffold Laravel 13 + Inertia/React/TypeScript + Tailwind + Sail (Postgres).
- Skema domain: suppliers, ingredients, products, recipe_items, stock_movements,
  transactions, sales, price_histories, expenses (uang sebagai `decimal`).
- Service layer: COGS, Inventory, Sale, TaxSimulator (PP 23 vs normal), Margin, P&L.
- API bot ber-Sanctum: `POST /api/transactions`, `POST /api/sales`,
  `GET /api/stock`, `GET /api/reports/today` + rate limiting + `wolee:bot-token`.
- Dashboard 9 halaman dengan RBAC owner/admin, export P&L ke Excel.
- Seeder data contoh dan dokumentasi setup/deploy di `README.md`.

[Unreleased]: https://github.com/kakapratama12/Wol-ee/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/kakapratama12/Wol-ee/releases/tag/v0.1.0
