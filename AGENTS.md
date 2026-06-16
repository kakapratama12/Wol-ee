# AGENTS.md — Wol-ee

Kiblat untuk semua kontributor (manusia & AI agent) di repo ini. Baca ini sebelum menulis kode.

## 1. Produk

**Wol-ee** — AI Business Assistant untuk UMKM F&B (kafe, bakery, kedai kopi).
Hybrid: **Telegram Bot** (Python, sudah ada, di luar repo ini) + **Web Dashboard** (repo ini).

Positioning: bukan pengganti POS, tapi "amplifier" yang bikin admin UMKM seexpert konsultan pajak, akuntan, dan inventory planner. Detail lengkap di [docs/PRD-Wol-ee.md](docs/PRD-Wol-ee.md), [docs/Roles-Sequence-Diagram-Wol-ee.md](docs/Roles-Sequence-Diagram-Wol-ee.md), [docs/Development-Context-Wol-ee.md](docs/Development-Context-Wol-ee.md).

## 2. Domain Glossary (WAJIB paham)

- **Ingredient (bahan baku)** — disimpan dalam *base unit*. Dua tipe: `gramasi` (g, ml, butir) dan `packaged` (sachet, pack, botol).
- **Product (produk jadi)** — yang dijual (Matcha Latte, Croissant). Punya `selling_price`.
- **Recipe** — gramasi tiap ingredient per 1 porsi produk.
- **COGS** (Cost of Goods Sold) = Σ(qty resep × harga per base unit ingredient). Auto-recalc saat harga bahan / resep berubah.
- **Waste %** — persentase susut wajar F&B (5–15%), dipakai di tax simulator. `COGS_with_waste = COGS × (1 + waste%)`.
- **PP 23/2018** — pajak final UMKM: `0.5% × omset`.
- **Normal taxation** — `(omset − COGS − expense) × rate`. Rate: Perorangan = PPh 21 progresif (5–35%), CV/PT Badan = 22%.
- **Margin** = `(selling_price − COGS) / selling_price`.
- **Stock alert**: Aman (`stok > minimum`), Menipis (`stok ≤ minimum`), Kritis (`stok < 50% minimum`).

## 3. Tech Stack

- **Backend**: Laravel 13 (PHP 8.5)
- **Frontend**: Inertia.js + React + TypeScript (Vite), Tailwind CSS 3 + shadcn/ui
- **DB**: PostgreSQL (shared dengan bot di produksi)
- **Auth**: Breeze (session, web) + Sanctum token (API bot)
- **Excel**: phpoffice/phpspreadsheet langsung (maatwebsite/excel belum support L13/PHP8.5)
- **Dev env**: Laravel Sail (Docker) dengan service Postgres
- **Test**: Pest

## 4. Struktur Folder

```
app/
  Http/
    Controllers/        # tipis, delegasi ke Services
    Controllers/Api/    # endpoint untuk bot
    Requests/           # SEMUA validasi di sini (Form Request)
    Middleware/
  Models/
  Services/             # business logic: Inventory, Cogs, TaxSimulator, Margin, Pnl
  Policies/             # role gating (owner/admin)
  Exports/              # Excel exports
database/
  migrations/
  seeders/
resources/
  js/
    Pages/              # Inertia pages (React)
    Components/         # komponen reusable + shadcn ui/
    Layouts/
    lib/                # util bersama (mis. formatRupiah)
routes/
  web.php               # dashboard (Inertia)
  api.php               # endpoint bot (Sanctum)
tests/
  Feature/  Unit/
docs/                   # PRD, sequence diagram, dev context
```

## 5. Production-Grade Standards

- **Uang**: simpan sebagai integer rupiah (atau `decimal`), **JANGAN PERNAH float**. Hitung di backend, frontend hanya format tampilan.
- **Controller tipis**: business logic hidup di `app/Services`. Bot (API) dan web (Inertia) memakai service yang sama — jangan duplikasi logika.
- **Validasi**: selalu via Form Request, bukan di controller.
- **Atomic**: setiap mutasi stok + pencatatan sale/transaction dibungkus `DB::transaction()`.
- **Stok sebagai ledger**: tulis `stock_movements`; `ingredients.current_stock` adalah turunan yang di-update di transaksi yang sama.
- **API bot**: auth Sanctum token + rate limit. Bot TIDAK query DB langsung — selalu lewat API.
- **Skema**: hanya lewat migration + seeder. Tidak ada edit DB manual.
- **Secrets**: jangan commit. `.env.example` harus lengkap & up-to-date.
- **Test wajib** untuk service kritis: `CogsService`, `TaxSimulatorService`.
- **Bahasa**: UI & pesan ke user dalam Bahasa Indonesia (PRD). Kode/identifier dalam Bahasa Inggris.

## 6. Perintah Penting

```bash
./vendor/bin/sail up -d            # start dev (app + postgres)
./vendor/bin/sail artisan migrate  # migrasi
./vendor/bin/sail artisan db:seed  # data contoh
npm run dev                        # vite (frontend)
./vendor/bin/sail test             # jalankan test (Pest)
```

## 7. Definition of Done (tiap fitur)

1. Migration + model + factory (kalau perlu).
2. Service + Form Request + Controller (web dan/atau API).
3. Inertia page (kalau ada UI), pakai shadcn + formatRupiah.
4. Test untuk logika kritis.
5. Tidak ada linter error; uang tidak pakai float; mutasi data atomic.
