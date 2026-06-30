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
- **Loose coupling, high cohesion**: Fungsi yang sama HARUS satu component. Jangan duplikasi modal/form/logic di tempat berbeda. Satu sumber kebenaran = satu component. Prinsip: DRY (Don't Repeat Yourself). Kalau fungsi sama, extract ke shared component. Ini mencegah bug fix harus dua kali dan regression risk.
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
./vendor/bin/sail artisan queue:work  # worker (proses job async, mis. alert stok)
./vendor/bin/sail test             # jalankan test (Pest)
```

## 7. Definition of Done (tiap fitur)

1. Migration + model + factory (kalau perlu).
2. Service + Form Request + Controller (web dan/atau API).
3. Inertia page (kalau ada UI), pakai shadcn + formatRupiah.
4. Test untuk logika kritis.
5. Tidak ada linter error; uang tidak pakai float; mutasi data atomic.
6. CHANGELOG di-update bila perubahan user-facing (lihat §8).

## 8. Engineering Workflow

Aturan ini yang dipakai agent untuk memutuskan **kapan** commit, checkpoint,
update changelog, dan tulis ADR — tanpa perlu ditanya tiap kali.

### 8.0 Sinkron dari GitHub (WAJIB sebelum mulai kerja)

Dokumen yang di-update **tim lewat GitHub** (terutama `task.md`, `docs/PRD-*`,
`CHANGELOG.md`) — **jangan andalkan salinan lokal/Cursor saja**.

Alur agent setiap sesi:

1. `git fetch origin`
2. `git checkout develop && git pull origin develop` (branch integrasi tim)
3. Baru baca `task.md` dan dokumen planning lainnya

**Source of truth = remote GitHub, branch `develop`.** Lokal bisa ketinggalan
kalau tim push lebih dulu.

### 8.1 Branch & commit

Model branch (Git Flow ringan):

| Branch | Peran |
|--------|--------|
| **`develop`** | Integrasi harian tim. PR feature masuk ke sini. `task.md` & sprint plan hidup di sini. |
| **`main`** | Production-ready. Isi = yang ter-deploy (VPS). Merge dari `develop` saat rilis. |
| **`feat/…`, `fix/…`, `chore/…`** | Feature branch pendek. PR target = **`develop`** (bukan langsung `main`). |

- **Conventional Commits**: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`,
  `chore:`, `ci:`, `security:`. Scope opsional, mis. `feat(inventory): ...`.
- **Satu commit = satu unit logis** yang utuh. Bukan dump besar, bukan WIP receh.
- Tulis *kenapa* di body commit bila tidak jelas dari judul.
- Hotfix produksi: `fix/…` dari `main` → PR ke `main` → cherry-pick/back-merge ke `develop`.

### 8.2 Checkpoint (kapan commit/aman)

Buat checkpoint (commit di state stabil, idealnya test hijau):

- **Sebelum** perubahan besar/berisiko (refactor luas, ganti dependency, migrasi).
- **Sesudah** menyelesaikan satu unit logis dan test relevan lulus.
- Sebelum berpindah konteks ke tugas lain yang tidak berkaitan.

Jangan checkpoint di tengah keadaan yang gagal kompilasi/test merah (kecuali
sengaja menyimpan WIP di branch sendiri dengan label jelas).

### 8.3 CHANGELOG (kapan update)

Update bagian `[Unreleased]` di `CHANGELOG.md` **jika** perubahan terlihat/
berdampak ke pengguna atau operator:

- **Update untuk**: fitur baru (`Added`), perbaikan bug (`Fixed`), perubahan
  perilaku (`Changed`), breaking change, isu keamanan (`Security`),
  deprecation (`Deprecated`), penghapusan (`Removed`).
- **TIDAK perlu** untuk: refactor internal, test, tooling/CI, format kode,
  komentar/dokumen internal yang tidak mengubah perilaku.
- Saat rilis: pindahkan `[Unreleased]` → versi ber-tag (lihat §8.5).

### 8.4 ADR (kapan tulis)

Tulis ADR baru di `docs/adr/` (lihat indeks `docs/adr/README.md`) **jika**:

- Memilih di antara alternatif dengan **trade-off nyata** (DB, auth, queue
  driver, struktur arsitektur, dependency besar), atau
- Keputusan **mahal/sulit dibalik** di kemudian hari.

**Jangan** tulis ADR untuk hal sepele (penamaan, formatting, pilihan yang
gampang diganti). Keputusan yang menggantikan ADR lama: tandai yang lama
`Superseded by ADR-XXXX`.

### 8.5 Versioning & rilis

- **SemVer** (`MAJOR.MINOR.PATCH`). MVP awal = `v0.1.0`.
- Rilis = pindahkan changelog `[Unreleased]` ke versi, lalu **tag** git
  (`vX.Y.Z`) di `main`. Bump: `PATCH` untuk fix, `MINOR` untuk fitur
  backward-compatible, `MAJOR` untuk breaking.

### 8.6 Security baseline (selalu)

- Secrets tidak pernah di-commit; `.env.example` lengkap & up-to-date.
- Validasi via Form Request; otorisasi via Policy/middleware (owner/admin).
- API bot: Sanctum token + rate limit. Security headers via middleware.
- Jalankan audit dependency saat menambah/menaikkan paket
  (`composer audit`, `npm audit`). Lihat `SECURITY.md`.

### 8.7 Async / queue (kapan dipakai)

Pindahkan ke queue (event + listener `ShouldQueue`) bila pekerjaan: lambat,
memanggil layanan eksternal (Telegram, email), atau tidak boleh menggagalkan
request inti (mis. notifikasi stok). Request inti tetap sinkron & atomic.
Lihat ADR-0005.
