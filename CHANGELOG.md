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

- Nomor PO (opsional) di invoice — tampil di PDF dan halaman detail hanya jika diisi.
- Status Draft invoice — flow: draft → outstanding → partial → paid. Draft bisa diedit bebas.
- Hapus invoice (draft & outstanding) dan arsipkan invoice (partial & paid) dari halaman list & detail.
- Filter arsip di halaman invoices — toggle "Tampilkan Arsip" / "Sembunyikan Arsip".
- Tombol "Simpan Draft" & "Simpan & Kirim" di form buat invoice.
- ESLint + Prettier: guardrails untuk code style konsisten. Frontend code otomatis ter-format.
- Idempotency key di sales, transactions, invoices — mencegah duplikat dari double submit atau network retry. UUID di-generate otomatis di frontend, backend cek sebelum create.
- Dark mode: toggle button di header, localStorage persistence, auto-detect system preference. Logo otomatis switch (navy → putih) berdasarkan theme.
- Terminology: "Tenant" → "Usaha", "Owner" → "Pengelola", "Admin" → "Staff" di seluruh UI dan database.
- Super Admin: halaman Platform Users (daftar semua user, search, filter per usaha) dan Platform Usaha (daftar usaha dengan detail pengelola/staff, expandable rows).
- Form "Tambah Usaha" di Platform Usaha: nama usaha, plan, data pengelola. Slug auto-generate dari nama.
- Dashboard date filter: pilihan Minggu Ini, Bulan Ini, 3 Bulan Terakhir, dan Custom (date picker).
- Brand color palette: Deep Navy (primary), Warm Amber (warning), Teal (success), Off White (background), Charcoal (text), Slate (muted), Light Gray (border).
- Favicon, apple-touch-icon, dan sidebar/login logo dari brand image yang di-upload.
- Report design standard: angka operasional selalu positif (tanpa minus/kurung). Hanya Laba/Rugi yang pakai hijau/merah.

### Changed

- Dashboard metrics, penjualan terbaru, dan pembelian terbaru sekarang ikut filter tanggal yang dipilih.
- PnL: hapus kuantitas (pcs) dari breakdown revenue & COGS. COGS & Expenses tampil sebagai angka biasa.
- Cashflow: Kas Keluar tampil sebagai angka biasa (bukan dalam kurung). Saldo Akhir saja yang pakai warna.
- Typography: Figtree untuk semua (body + headline), JetBrains Mono dihapus.
- Sidebar dan login page pakai logo brand image (bukan SVG default).

### Fixed

- 500 error saat simpan penjualan/pembelian — `$idempotencyKey` tidak di-pass ke closure `DB::transaction` di SaleService & InventoryService.
- Quantity di PDF preview invoice tampil desimal (50.0000 → 50).
- Race condition: stock deduction sekarang pake `lockForUpdate()` di InventoryService (recordUsage, reversePurchase, reverseSaleUsage, deductFinishedGoods) dan ProductionRunService. Mencegah oversell dari concurrent sales/production runs.
- PnL server error: selectRaw terhapus oleh sed command, menyebabkan GROUP BY error.
- Favicon background: dari abu-abu (JPEG) menjadi transparan.

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
