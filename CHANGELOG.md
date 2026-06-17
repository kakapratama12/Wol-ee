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

### Changed

- Dokumentasi produk (`docs/PRD`, `docs/Roles-Sequence-Diagram`, `docs/Development-Context`)
  diperbarui: prioritas fitur (Partner, Aging, Invoice tracking P1), MVP scope
  diselaraskan dengan implementasi v0.1.0, stack Inertia/React, status deploy.

### Added

- Sprint 2 API: Partner CRUD, Invoice tracking (create, pay, outstanding), aging report
  (`/api/partners`, `/api/invoices`, `/api/reports/aging`).
- Seeder sample partner & invoice untuk tenant `kafe-contoh`.
- Workflow rekayasa: kebijakan commit, changelog, dan ADR di `AGENTS.md`.
- `CHANGELOG.md` dan direktori `docs/adr/` (Architecture Decision Records).
- Fondasi queue: event `SaleRecorded` + listener `SendLowStockAlert` (queued)
  yang mengirim peringatan stok menipis/kritis ke Telegram setelah penjualan.
- Middleware `SecurityHeaders` untuk security headers di semua response web.
- `SECURITY.md`: checklist keamanan & prosedur audit dependency.

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
