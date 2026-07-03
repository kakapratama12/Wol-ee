# Wol-ee Regression QA Checklist

**Tujuan:** Pastikan semua user flow masih jalan setelah ada perubahan code.
**Cara pakai:** Pilih tier sesuai perubahan → jalankan item → laporkan pass/fail.
**Update rule:** Setiap ada fitur baru, tambahkan item ke checklist ini.

## Tier System

| Tier | Kapan pakai | Estimasi | Coverage |
|------|-------------|----------|----------|
| **Tier 1** | Setiap deploy/commit (wajib) | ~5 min | Critical user flows |
| **Tier 2** | Setelah perubahan struktural (route, middleware, layout) | ~15 min | Semua page load + navigation |
| **Tier 3** | Setelah perubahan fitur spesifik | +5 min per area | Deep test area yang berubah |

## Environment

- **Staging:** https://staging.wolee.my.id
- **Single outlet (Chockles):** `kasir@chockles.test` / `owner@chockles.test` / password: `password`
- **Multi outlet (Cafe Contoh):** `kasir@wol-ee.local` / `owner@wol-ee.local` / password: `password`
- **VPS:** 43.157.240.175
- **Repo:** `/var/www/wol-ee`

---

## TIER 1 — Critical Flows (setiap deploy)

### A. Business Type Validation (WAJIB — run untuk SINGLE + MULTI)

**Kapan:** Setiap deploy. Test dengan Chockles (single) DAN Cafe Contoh (multi).

#### A1. Menu Visibility — Single Outlet

| # | Test | Expected |
|---|------|----------|
| A1.1 | "Kelola Outlet" di sidebar | **TIDAK ADA** |
| A1.2 | "Distribusi" di sidebar | **TIDAK ADA** |
| A1.3 | "Biaya outlet" checkbox di form expense | **TIDAK ADA** |
| A1.4 | Outlet picker di Buka Sesi | **TIDAK ADA** (langsung buka) |

#### A2. Menu Visibility — Multi Outlet

| # | Test | Expected |
|---|------|----------|
| A2.1 | "Kelola Outlet" di sidebar | **ADA** |
| A2.2 | "Distribusi" di sidebar | **TIDAK ADA** (ada under Kelola Outlet, bukan item terpisah) |
| A2.3 | "Biaya outlet" checkbox di form expense | **ADA** |
| A2.4 | Outlet picker di Buka Sesi | **TIDAK ADA** (1 staff = 1 outlet, auto-login ke outlet yang di-assign) |

#### A3. Data Source — Single Outlet

| # | Test | Expected |
|---|------|----------|
| A3.1 | POS register → stok bahan | dari `ingredients.current_stock` |
| A3.2 | Adjust stok → stok berubah | `ingredients.current_stock` berubah |
| A3.3 | Sale → stok berkurang | `ingredients.current_stock` berkurang |
| A3.4 | Tidak ada `outlet_inventories` | tabel kosong atau tidak dipakai |

#### A4. Data Source — Multi Outlet

| # | Test | Expected |
|---|------|----------|
| A4.1 | POS register → stok bahan | dari `outlet_inventories` per outlet |
| A4.2 | Adjust stok → stok berubah | `outlet_inventories` berubah |
| A4.3 | Sale → stok berkurang | `outlet_inventories` berkurang |
| A4.4 | Distribusi → stok berpindah | `ingredients` berkurang, `outlet_inventories` bertambah |

#### A5. Cross-type Isolation

| # | Test | Expected |
|---|------|----------|
| A5.1 | Chockles login → tidak lihat data Cafe Contoh | isolated |
| A5.2 | Cafe Contoh login → tidak lihat data Chockles | isolated |
| A5.3 | Cafe Contoh kasir → pilih outlet → stok beda per outlet | correct per outlet |

**Business Type Validation Total: ~15 items**

### B. Owner Login & Dashboard

| # | Test | Single | Multi |
|---|------|--------|-------|
| B1 | Owner login → landing page `/dashboard` | works | works |
| B2 | Dashboard: "Stok Perlu Perhatian" muncul | shows items if stock ≤ min | shows items if stock ≤ min |
| B3 | Menu "Kelola Outlet" tersembunyi untuk single | hidden | visible |

### C. POS Kasir Flow (Critical Path)

| # | Test | Single | Multi |
|---|------|--------|-------|
| C1 | Kasir login → landing `/pos` | works | works |
| C2 | Buka Sesi — pilih outlet (multi) / langsung (single) | no picker | picker shown |
| C3 | POS register: produk muncul dengan harga | products listed | products listed |
| C4 | Tambah item ke cart, ubah qty | cart updates | cart updates |
| C5 | Proses bayar (tunai) | sale recorded | sale recorded |
| C6 | Tombol "Bayar" disable jika cart kosong | disabled | disabled |
| C7 | Nama outlet muncul di POS | shows name | shows name |
| C8 | Hari Ini: order terdaftar dengan benar | orders listed | orders listed |

### D. Stok (POS)

| # | Test | Single | Multi |
|---|------|--------|-------|
| D1 | Adjust Stok berhasil | works | works |
| D2 | Pembelian Bahan berhasil | works | works |
| D3 | Distribusi — Gudang Pusat ke Outlet | N/A | works |

### E. Pengeluaran

| # | Test | Single | Multi |
|---|------|--------|-------|
| E1 | Tambah pengeluaran berhasil | works | works + outlet tag |
| E2 | Checkbox outlet tersembunyi untuk single | hidden | visible |

### F. Security

| # | Test |
|---|------|
| F1 | Kasir tidak bisa akses `/settings/*` (403) |
| F2 | Kasir tidak bisa akses `/staff` (403) |
| F3 | Single outlet tidak bisa akses `/outlets` (403) |

**Tier 1 Total: ~33 items**

---

## TIER 2 — Smoke Test (page load tanpa error)

**Kapan:** Perubahan route, middleware, layout, atau dependency upgrade.
**Cara:** Buka setiap URL, pastikan tidak 500/blank. Cukup load, tidak perlu interaksi.
**Penting:** Run untuk Chockles (single) DAN Cafe Contoh (multi) — ada page yang harusnya 403/hidden untuk tipe tertentu.

### G. Owner Pages (smoke load — BOTH types)

| # | URL | Page |
|---|-----|------|
| G1 | `/dashboard` | Dashboard utama |
| G2 | `/inventory` | Inventaris bahan baku |
| G3 | `/products` | Daftar produk |
| G4 | `/transactions` | Transaksi |
| G5 | `/sales` | Laporan penjualan |
| G6 | `/expenses` | Pengeluaran |
| G7 | `/pnl` | Laba Rugi |
| G8 | `/pnl/export` | Export P&L |
| G9 | `/margin` | Margin analysis |
| G10 | `/reports/cashflow` | Cashflow |
| G11 | `/reports/aging` | Aging report |
| G12 | `/tax` | Tax simulator |
| G13 | `/partners` | Mitra usaha |
| G14 | `/invoices` | Tagihan |
| G15 | `/payables` | Hutang supplier |
| G16 | `/staff` | Manajemen staf |
| G17 | `/profile` | Profil user |
| G18 | `/settings/company` | Pengaturan usaha |
| G19 | `/settings/bot` | Integrasi bot |

### H. Outlet Pages (Multi only — Single should 403/not visible)

| # | URL | Multi (Cafe Contoh) | Single (Chockles) |
|---|-----|---------------------|-------------------|
| H1 | `/outlets` | loads ✓ | 403 or hidden |
| H2 | `/outlets/{id}` | loads ✓ | 403 or hidden |
| H3 | `/outlets/{id}/inventory` | loads ✓ | 403 or hidden |
| H4 | `/outlets/{id}/stock/movements` | loads ✓ | 403 or hidden |
| H5 | `/distributions` | loads ✓ | 403 or hidden |
| H6 | `/production-runs` | loads ✓ | loads ✓ |
| H7 | `/finished-goods` | loads ✓ | loads ✓ |
| H8 | `/prep-stocks` | loads ✓ | loads ✓ |

### I. POS Pages (Kasir)

| # | URL | Page |
|---|-----|------|
| I1 | `/pos` | POS landing |
| I2 | `/pos/register` | Register (jika sesi aktif) |
| I3 | `/pos/today` | Hari ini |
| I4 | `/pos/stock` | Stok overview |
| I5 | `/pos/stock/adjust` | Form adjust |
| I6 | `/pos/stock/purchase` | Form pembelian |
| I7 | `/pos/stock/movements` | Riwayat mutasi |
| I8 | `/pos/session/summary` | Ringkasan sesi |

**Tier 2 Total: ~44 items**

---

## TIER 3 — Deep Test (per area)

**Kapan:** Fitur tertentu diubah → test semua flow di area itu.

### J. POS Deep Test

| # | Test |
|---|------|
| J1 | Multi-item transaksi (2+ produk, beda qty) |
| J2 | Bayar dengan card/e-wallet |
| J3 | Bayar dengan jumlah pas (no change) |
| J4 | Void sale (jika ada menu) |
| J5 | Receipt/struk setelah bayar |
| J6 | Session summary ditampilkan setelah tutup sesi |
| J7 | Skip summary |
| J8 | Stok berkurang setelah transaksi (data integrity) |
| J9 | Buka sesi baru setelah sesi sebelumnya ditutup |
| J10 | Product search/filter di register |

### K. Stok Deep Test

| # | Test |
|---|------|
| K1 | Adjust stok → stok berubah sesuai input |
| K2 | Stok negatif diizinkan (info only) |
| K3 | Stock movements tercatat |
| K4 | Purchase → stok bertambah |
| K5 | Distribusi Gudang Pusat → outlet_inventories bertambah, ingredients berkurang |
| K6 | Distribusi Antar Outlet → from berkurang, to bertambah |
| K7 | History distribusi lengkap |

### L. Expense Deep Test

| # | Test |
|---|------|
| L1 | Tambah expense tanpa outlet (single) |
| L2 | Tambah expense dengan outlet (multi) |
| L3 | Edit expense |
| L4 | Hapus expense |
| L5 | Filter by tanggal |
| L6 | Filter by outlet (multi) |

### M. Invoice Deep Test

| # | Test |
|---|------|
| M1 | Create invoice |
| M2 | Edit invoice |
| M3 | Pay invoice (partial/full) |
| M4 | Archive invoice |
| M5 | Generate PDF |
| M6 | Generate kuitansi |

### N. Partner Deep Test

| # | Test |
|---|------|
| N1 | Tambah mitra (supplier/customer) |
| N2 | Edit mitra |
| N3 | Hapus mitra |
| N4 | Detail mitra (aging, transaksi) |

### O. Settings Deep Test

| # | Test |
|---|------|
| O1 | Update company settings |
| O2 | Tambah/edit outlet (multi) |
| O3 | Generate bot token |

### P. Navigation Deep Test

| # | Test |
|---|------|
| P1 | Bottom navbar semua link works |
| P2 | Owner sidebar semua menu accessible |
| P3 | Breadcrumb navigation (jika ada) |
| P4 | Back button behavior |
| P5 | Mobile responsive — POS usable di HP |
| P6 | Mobile responsive — Owner pages usable di HP |

### Q. Data Integrity

| # | Test |
|---|------|
| Q1 | Sale recorded → stok berkurang |
| Q2 | Void sale → stok kembali |
| Q3 | Expense tagged outlet → muncul di P&L outlet |
| Q4 | Distribution → source berkurang, target bertambah |
| Q5 | Tutup sesi → total cash sesuai actual |

### R. Business Type Deep Test

| # | Test | Type |
|---|------|------|
| R1 | Single: adjust stok → `ingredients.current_stock` berubah, `outlet_inventories` TIDAK berubah | Single |
| R2 | Single: sale → `ingredients.current_stock` berkurang langsung | Single |
| R3 | Multi: adjust stok → `outlet_inventories` berubah, `ingredients` TIDAK berubah | Multi |
| R4 | Multi: sale → `outlet_inventories` berkurang, `ingredients` TIDAK berubah | Multi |
| R5 | Multi: distribusi Gudang Pusat → `ingredients` berkurang, `outlet_inventories` bertambah | Multi |
| R6 | Multi: distribusi Antar Outlet → from `outlet_inventories` berkurang, to bertambah | Multi |
| R7 | Multi: kasir pilih outlet A → hanya lihat stok outlet A | Multi |
| R8 | Multi: kasir pindah outlet → stok berubah sesuai outlet baru | Multi |
| R9 | Single: dashboard stok warning = `ingredients.current_stock` ≤ minimum | Single |
| R10 | Multi: dashboard stok warning = `outlet_inventories` ≤ minimum per outlet | Multi |

---

## Total Items

| Tier | Items |
|------|-------|
| Tier 1 (Critical + Business Type + Security) | ~33 |
| Tier 2 (Smoke) | ~44 |
| Tier 3 (Deep) | ~40 |
| **Grand Total** | **~117** |

---

## Pitfalls

1. **Owner lands on `/dashboard`, not `/pos`** — `/pos` is the POS interface. Owner's landing is `/dashboard`. Don't mark as FAIL.
2. **Fresh context per role**: owner and kasir MUST use separate `browser.new_context()`. Session cookies overlap within a context.
3. **POS payment buttons**: button text varies (Bayar/Konfirmasi/Submit). Inspect actual DOM before writing selectors.
4. **CSS selector syntax**: `[class*='order']` in `locator.count()` can fail with token errors. Use `.filter(has_text=...)` instead.

## Business Type Notes

- **Single outlet** = Chockles (`owner@chockles.test`). No outlet concept. Stock from `ingredients.current_stock`.
- **Multi outlet** = Cafe Contoh (`owner@wol-ee.local`). Has outlets. Stock from `outlet_inventories`.
- Pages yang harus HIDDEN/403 untuk single: `/outlets`, `/distributions`, outlet picker di POS.
- Pages yang boleh diakses keduanya: `/production-runs`, `/finished-goods`, `/prep-stocks`, semua owner pages lainnya.
- Data isolation: Chockles tidak bisa lihat data Cafe Contoh dan sebaliknya.

---

*Last updated: 2026-07-03*
