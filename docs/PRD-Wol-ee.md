# PRD: Wol-ee — AI Business Assistant untuk UMKM F&B

## 1. Product Overview

**Nama:** Wol-ee

**Tagline:** "POS buat jualan. Wol-ee buat ngerti bisnis kamu."

**Product Type:** Hybrid — Telegram Bot + Web Dashboard

**Target User:** UMKM F&B (Food & Beverage) — kafe, bakery, kedai kopi

**Positioning:** Bukan replacement POS. Tapi amplifier yang bikin admin seexpert konsultan pajak, akuntan, dan inventory planner.

---

## 2. Problem Statement

UMKM F&B punya pain points yang saling terhubung:

### 2.1 Inventory Chaos
- Beli bahan dalam kilogram, tapi POS catat dalam satuan produk (pcs/sachet)
- Stok dihitung ngira-ngira berdasarkan penjualan
- Gak ada visibility real-time sisa stok
- Sering kehabisan bahan atau overstock
- **POS exist gak bisa handle gramasi**

### 2.2 COGS Gak Jelas
- Gak tau berapa actual cost per produk
- Margin dihitung ngira-ngira
- Gak tau produk mana yang sebenernya untung/rugi

### 2.3 Tax Anxiety
- "Laporan real = pajak naik" (false belief)
- Padahal COGS yang akurat bisa NURUNIN pajak (legal)
- UMKM gak tau ada 2 skema pajak: PP 23 vs Normal
- Takut lapor, akhirnya gak lapor sama sekali

### 2.4 Double Input
- Transaksi masuk di POS, tapi inventory dihitung manual
- Data gak nyambung, sering miss

### 2.5 Production Ga Kecatat
- Bakery/katering punya workflow: bahan baku -> produksi -> produk jadi
- Ga ada track kapan produksi, berapa yield aktual, berapa waste
- Owner ga tau margin real karena ga tau actual yield per batch
- COGS ga akurat karena yield yang dipake adalah asumsi, bukan aktual

---

## 3. Core Insight

> **Wol-ee bukan replacement untuk POS atau admin. Tapi amplifier yang bikin admin seexpert konsultan pajak, akuntan, dan inventory planner — tanpa harus jadi expert.**

### Market Gap

```
POS (existing):
✅ Receipt printing
✅ Basic sales tracking
❌ Inventory gramasi
❌ COGS calculation
❌ Tax planning
❌ Natural language interface

Wol-ee:
❌ Receipt printing (Phase 3)
✅ Inventory gramasi
✅ COGS calculation
✅ Tax planning (simulator)
✅ Natural language interface (Telegram bot)
```

### Analogi:
| Tool | Bukan replacement untuk | Tapi amplifier untuk |
|------|------------------------|---------------------|
| Excel | Akuntan | Akuntan jadi lebih cepat |
| CRM | Sales | Sales jadi lebih organized |
| POS | Kasir | Kasir jadi lebih cepat |
| **Wol-ee** | Admin UMKM | Admin jadi lebih expert |

### Why "Wol-ee"?
- Simple, memorable
- Friendly (cocok untuk UMKM)
- Bisa jadi brand character nanti

---

## 4. User Roles

### 4.1 Owner/Business Owner
**Akses:** Full access
**Kebutuhan:**
- Lihat P&L (profit & loss)
- Tax planning & simulator
- Inventory overview
- Cash flow monitoring

**Interaksi dengan Bot:**
- "Stok hari ini"
- "Berapa profit bulan ini?"

**Interaksi dengan Dashboard:**
- Lihat semua laporan
- Setup resep & gramasi
- Tax simulator
- Export laporan

### 4.2 Admin/Staff
**Akses:** Limited (transaksi, inventory)
**Kebutuhan:**
- Input transaksi harian
- Cek stok
- Input pembelian bahan
- Record penjualan

**Interaksi dengan Bot:**
- "Beli tepung 2kg 40 ribu"
- "Jual matcha 10 biji"
- "Stok susu berapa?"

**Interaksi dengan Dashboard:**
- Lihat inventory
- Lihat transaksi harian

---

## 5. Feature Map

### 5.1 MVP (Phase 1) — Core Value

#### Bot Features (Telegram)
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Transaction Input** | NL input: "Beli tepung 2kg 40k" | P0 |
| **Sales Input** | NL input: "Jual matcha 10 biji" | P0 |
| **Stock Check** | "Stok hari ini" | P0 |
| **COGS Estimate** | Auto-hitung bahan terpakai per penjualan | P0 |
| **Stock Alert** | Notif otomatis stok menipis | P1 |
| **Quick Report** | "Profit hari ini" | P1 |

#### Dashboard Features (Web)
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Dashboard Overview** | Omset, COGS, Profit, Margin | P0 |
| **Transaction List** | Daftar semua transaksi | P1 |
| **Inventory Management** | Stok, value, history | P1 |
| **Recipe Management** | Resep + gramasi produk | P1 |
| **Production Run** | Catat produksi: bahan terpakai, yield aktual, waste | P1 |
| **Partner Management** | Daftar customer & supplier | P1 |
| **Partner Aging** | Siapa yang belum bayar, umur berapa lama | P1 |
| **Invoice Tracking** | Daftar invoice outstanding | P1 |
| **P&L Report** | Laporan laba rugi + export Excel | P1 |
| **Tax Simulator** | Kalkulasi estimasi pajak | P1 |
| **Margin Protection** | Price tracker, margin alert, what-if | P0 |
| **User Settings** | Profil, role, password | P2 |

### 5.2 Phase 2 — Finance & Reporting

#### Bot Features
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Cash Flow Query** | "Cash flow minggu ini" | P1 |

#### Dashboard Features
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Cash Flow** | Arus kas harian/mingguan/bulanan | P1 |
| **Multi-user** | Admin, Staff, Owner | P2 |

### 5.3 Phase 3 — Expansion

#### Bot Features
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Email Parsing** | Auto-import transaksi dari email bank | P2 |
| **B2B Orders** | Input order B2B, hitung bahan | P2 |
| **Receivables Tracking** | "Siapa yang masih hutang?" | P2 |
| **Invoice Generation** | "Bikin invoice untuk Mariot" | P2 |

#### Dashboard Features
| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Invoice Management** | Buat, kirim, track invoice | P2 |
| **Receivables** | Daftar piutang | P2 |
| **Export Reports** | PDF export | P2 |
| **POS Integration** | Receipt printing (kalau demand ada) | P3 |

---

## 6. Key User Flows

### 6.1 Flow: Input Transaksi (Bot)

```
User: "Beli tepung 2kg 40 ribu sama susu 1 liter 18 ribu"

Bot: ✅ Tercatat!
     📦 Stok tepung: 5kg (+2kg)
     📦 Stok susu: 3L (+1L)
     
     [Lihat Dashboard →]
```

### 6.2 Flow: Penjualan + COGS (Bot)

```
User: "Jual matcha 10 biji, coklat 20 biji"

Bot: ✅ Terjual!
     🔻 Estimasi terpakai:
        Tepung: 3kg
        Susu: 6L
        Telur: 60 biji
        Pasta Matcha: 200g
     
     💰 Revenue: Rp 1.250.000
     📉 COGS: Rp 185.000
     📈 Profit: Rp 1.065.000 (85%)
     
     ⚠️ Stok susu menipis: 2L (minimum 5L)
     
     [Lihat Dashboard →]
```

### 6.3 Flow: Tax Simulator (Dashboard)

```
Owner buka Dashboard → Tax Simulator

Input:
├── Pilih tipe bisnis: Perorangan / CV / PT
├── Omset: 72M
├── COGS: 28.8M (auto dari tracking)
├── Expense lain: 15M (input manual)
└── Waste %: 15% (input manual)

Output:
├── Profit taxable: 23.88M
├── Estimasi pajak (PP 23): 360K
├── Estimasi pajak (Normal): 1.19M
└── Selisih: 830K

💡 Disclaimer: Tool ini untuk estimasi. 
   Konsultasikan dengan konsultan pajak.
```

### 6.4 Flow: Production Run (Dashboard)

Owner buka Dashboard -> Produksi -> Input Produksi Baru

- Pilih Resep: Croissant (20 pcs/batch)
- Jumlah Batch: 2

Bahan Terpakai (auto-fill dari resep, bisa edit):
- Tepung terigu: 1.000g -> 1.000g (ok)
- Butter: 500g -> 480g (dikurangi sedikit)
- Gula: 100g -> 100g (ok)
- Ragi: 14g -> 14g (ok)

Yield Aktual: 38 pcs
Waste: 4 pcs (2 rusak, 2 expired)
Total Cost: Rp 47.500 (snapshot dari harga bahan hari ini)

Hasil:
- Stok Croissant: 38 pcs (+38)
- Bahan terpakai: Tepung 1kg, Butter 480g, dst
- Waste: 4 pcs (Rp 5.000) -> tercatat sebagai expense terpisah

### 6.5 Flow: P&L Report (Dashboard)

```
Owner buka Dashboard → Laporan → P&L

┌─────────────────────────────────────┐
│ P&L Report - Juni 2026             │
├─────────────────────────────────────┤
│ Revenue:        Rp 72.000.000      │
│ COGS:          (Rp 28.800.000)     │
│ Gross Profit:   Rp 43.200.000      │
│                                     │
│ Expenses:                         │
│ - Listrik:     (Rp 3.000.000)     │
│ - Sewa:        (Rp 5.000.000)     │
│ - Internet:    (Rp 500.000)       │
│ - Lainnya:     (Rp 6.500.000)     │
│ Total Expenses: (Rp 15.000.000)   │
│                                     │
│ Laba Bersih:    Rp 28.200.000      │
└─────────────────────────────────────┘

[Export ke Excel →]
```

---

## 7. Tax Strategy (Core Feature)

### 7.1 Skema Pajak Indonesia untuk UMKM

| Skema | Cara Hitung | Cocok untuk |
|-------|-------------|-------------|
| **PP 23/2018** | 0.5% dari omset (final) | UMKM dengan margin tinggi, gak mau ribet |
| **Normal** | (Omset - COGS - Ops) × progresif | UMKM dengan COGS besar, mau optimize |

### 7.2 Tipe Bisnis

| Tipe | Pajak | Keterangan |
|------|-------|------------|
| **Perorangan** | PPh 21 (progresif 5-35%) | UMKM kecil, simpel |
| **CV** | PPh 21 atau Badan (22%) | Fleksibel, bisa pilih |
| **PT** | PPh 25/29 (22%) | Wajib laporan audit |

### 7.3 Key Insight

> **Banyak UMKM takut "laporan real = pajak naik". Padahal:**
> - COGS yang akurat bisa jadi "shield" — profit turun = pajak turun
> - Waste percentage (5-15%) itu legitimate di F&B
> - User bisa adjust di Excel sebelum submit

### 7.4 Wol-ee's Role

1. **Track:** Catat transaksi & COGS real
2. **Simulate:** "Kalau COGS segini, pajaknya segini"
3. **Educate:** Jelaskan skema pajak yang tersedia
4. **Export:** Sediakan data dalam format Excel

### 7.5 Disclaimer

> *"Tool ini untuk perencanaan keuangan dan estimasi pajak. Bukan pengganti konsultan pajak. Konsultasikan laporan akhir dengan konsultan pajak atau akuntan bersertifikat."*

---

## 8. Margin Protection (Core Feature)

### 8.1 Masalah

UMKM F&B sering terjebak:
- Harga bahan baku naik terus
- Gak berani naikin harga jual (takut customer lari)
- Margin tergerus pelan-pelan
- Gak ada data buat justifikasi kenaikan harga

### 8.2 Solusi: Margin Protection

| Sub-fitur | Fungsi | Contoh |
|-----------|--------|--------|
| **Price Tracker** | Track harga bahan baku historis | "Tepung Jan: 18K, Jul: 22K (+22%)" |
| **Margin Alert** | Notif kalau margin turun | "⚠️ Margin Matcha turun dari 81% ke 79%" |
| **What-If Simulator** | Simulasi dampak kenaikan harga | "Kalau tepung naik 10%, margin jadi 77%" |
| **Price Recommendation** | Saran kenaikan harga jual | "Naikin harga 5% biar margin tetep 81%" |

### 8.3 User Flow

```
Owner buka Dashboard → Margin Protection

┌─────────────────────────────────────────┐
│ Margin Alert - Juni 2026               │
├─────────────────────────────────────────┤
│ ⚠️ Matcha Latte: Margin turun 2%       │
│    Penyebab: Harga tepung naik 22%     │
│    [Lihat Detail →]                     │
│                                         │
│ ⚠️ Croissant: Margin turun 1.5%        │
│    Penyebab: Harga butter naik 15%     │
│    [Lihat Detail →]                     │
└─────────────────────────────────────────┘

Klik "Lihat Detail" Matcha Latte:

┌─────────────────────────────────────────┐
│ Matcha Latte - Price History           │
├─────────────────────────────────────────┤
│ Harga Jual: Rp 45.000 (tetep)          │
│                                         │
│ COGS Histori:                          │
│ ├─ Jan: Rp 8.500 (margin 81%)          │
│ ├─ Apr: Rp 8.900 (margin 80%)          │
│ └─ Jul: Rp 9.300 (margin 79%)          │
│                                         │
│ What-If:                               │
│ ├─ Kalau tepung naik 10% lagi:         │
│ │   Margin jadi 77% (-2%)              │
│ │                                       │
│ ├─ Kalau mau margin tetap 81%:         │
│ │   Harga jual harus: Rp 47.200 (+5%) │
│ │                                       │
│ └─ [Export ke Excel]                    │
└─────────────────────────────────────────┘
```

---

## 9. Inventory Model

### 9.1 Dua Lapis Inventory

Wol-ee membedakan dua tipe inventory:

**Bahan Baku (`item_type: raw_material`)**
- Yang dibeli dari supplier
- Stok dikurangi saat dipakai produksi
- Contoh: tepung, mentega, telur, gula

**Produk Jadi (`item_type: finished_goods`)**
- Yang dijual ke customer
- Stok bertambah dari produksi, berkurang dari penjualan
- Contoh: roti, kue, minuman

> **Kenapa satu tabel?** Karena struktur stock, unit, price, supplier = sama. Field tambahan untuk finished goods (expiry, batch number) ditambah sebagai nullable nanti. Kalau field spesifik numpuk (3+), baru dipisah tabel.

### 9.2 Recipe Types

| Tipe | Deskripsi | Yield | Contoh |
|------|-----------|-------|--------|
| **Unit** | Resep per 1 porsi | Tetap (1 porsi = 1 produk) | Es jeruk, kopi |
| **Batch** | Resep per 1 batch produksi | Bervariasi per batch | Roti, kue, katering |

**Unit:** Quantity di recipe = per 1 porsi. Sales langsung kurangi bahan.
**Batch:** Quantity di recipe = per 1 batch. Harus lewat Production Run dulu.

### 9.3 Production Run

Production run adalah catatan setiap kali user melakukan produksi.

**Data yang dicatat:**
- Resep yang dipakai
- Jumlah batch
- Bahan yang benar-benar terpakai (editable, bukan auto dari resep)
- Yield aktual (berapa pcs/loyang yang dihasilkan)
- Total cost (snapshot dari harga bahan saat produksi, bukan live price)
- Waste (berapa yang rusak/gagal)

**Flow:**
1. User pilih resep (misal: Croissant)
2. Input jumlah batch (misal: 2 batch)
3. Isi bahan yang terpakai (auto-fill dari resep, tapi bisa diedit)
4. Input yield aktual (misal: 38 pcs dari 2 batch = 19/batch)
5. Input waste (misal: 4 pcs rusak)
6. Submit -> Stok bahan baku berkurang, stok produk jadi bertambah, waste dicatat

**Kenapa yield tidak di-lock?**
Karena di bakery, yield sering berubah-ubah:
- Batch 1: 20 pcs (sesuai resep)
- Batch 2: 18 pcs (adonan kurang kembang)
- Batch 3: 22 pcs (oven sudah panas sempurna)

Owner perlu tau yield aktual untuk hitung margin real, bukan asumsi.

### 9.4 Waste Tracking

Waste adalah produk yang gagal/rusak saat produksi. Dicatat terpisah dari COGS.

**Alasan dipisah:**
- Margin jelek = 2 kemungkinan: (1) harga bahan naik, atau (2) demand planning buruk
- Kalau waste dicampur COGS, owner ga bisa bedain mana yang bisa diperbaiki
- Waste bisa jadi deductible expense untuk pajak

**Jenis waste:**
- **Production waste:** Rusak saat produksi (adonan gagal, gosong)
- **Demand waste:** Produksi berlebih, tidak terjual (barang expired)

### 9.5 Stock Flow: Porsian vs Batch

**Porsian (Es Jeruk):**
1. Beli Jeruk 30 biji (Rp 60.000)
2. Jual Es Jeruk 10 gelas
3. Auto-deduct: 30 biji jeruk (3 biji x 10 gelas)
4. Stok Jeruk: 0 biji

**Batch (Croissant):**
1. Beli Tepung 5kg, Butter 3kg
2. Production Run: 2 batch
3. Input: Tepung 1kg, Butter 0.5kg terpakai
4. Yield: 38 pcs, Waste: 4 pcs
5. Stok Croissant: 38 pcs, Stok Tepung: 4kg, Stok Butter: 2.5kg
6. Waste Expense: 4 pcs x COGS/pcs

### 9.6 Alert System

| Status | Kondisi | Action |
|--------|---------|--------|
| Aman | Stok > minimum | - |
| Menipis | Stok < minimum | Alert ke bot |
| Kritis | Stok < 50% minimum | Urgent alert |

### 9.7 Warning Logic

**Validation keras (di Request/Model):**
- Bahan yang wajib diisi -> harus terisi
- Yield harus > 0

**Warning behavior (di Service layer):**
- Bahan resep baru 60% terisi -> warning "ada bahan yang belum dicatat?"
- Yield jauh dari resep (> 20% deviasi) -> warning "yield ini beda jauh dari resep"

> Warning ada di service layer, bukan model/migration. Hard validation di model, soft warning di service.

## 10. Tech Architecture (High Level)

```
┌─────────────────┐     ┌─────────────────┐
│  📱 Telegram    │────▶│  🐍 Python Bot  │
│  (User Input)   │◀────│  (NL Parse)     │
└─────────────────┘     └────────┬────────┘
                                 │
                          ┌──────▼──────┐
                          │  🔌 API     │
                          │  (Laravel)  │
                          └──────┬──────┘
                                 │
                    ┌────────────┼────────────┐
                    │            │            │
              ┌─────▼─────┐ ┌───▼───┐
              │  💾 DB    │ │ 🖥️ Web│
              │ (Postgres)│ │ (Dash)│
              └───────────┘ └───────┘
```

### 10.1 Components

| Component | Tech | Responsibility |
|-----------|------|----------------|
| **Bot** | Python + Telegram | NL input, quick queries |
| **API** | Laravel | Business logic, auth |
| **Dashboard** | Laravel + Blade | UI, reports, setup |
| **Database** | PostgreSQL | Store everything |

---

## 11. Pricing Strategy

### 11.1 Market Reference

| Product | Price | Features |
|---------|-------|----------|
| POS Competitor A | 900K/tahun (75K/bulan) | POS, basic sales |
| POS Competitor B | ~300K/bulan | POS, inventory basic |
| POS Competitor C | ~300K/bulan | POS, basic accounting |

### 11.2 Wol-ee Pricing (Proposed)

| Tier | Price | Features |
|------|-------|----------|
| **Free** | Rp 0 | Bot basic: transaksi, stok check |
| **Pro** | 49K/bulan (449K/tahun) | Dashboard, COGS, tax simulator, P&L |
| **Business** | 99K/bulan (899K/tahun) | Multi-user, invoice, B2B |

### 11.3 Value Proposition

> "Bayar 49K/bulan, tapi bisa hemat pajak 500K-1M/bulan dan protect margin dari kenaikan harga bahan baku. ROI within first month."

---

## 12. MVP Scope

### What's IN (MVP — sudah live v0.1.0):

**Bot (via API, bot Python perlu di-wire):**
- Transaction input via bot (NL)
- Sales input via bot (NL)
- Stock check & COGS estimate per penjualan

**Dashboard (web):**
- Dashboard overview (omset, COGS, profit, margin)
- Transaction list + input pembelian (form)
- Sales list + input penjualan (form)
- Inventory management (gramasi + packaged, status stok)
- Recipe management + COGS otomatis per produk
- Tax simulator (PP 23 vs normal)
- P&L report + export Excel
- Margin protection (price tracker, alert, what-if)
- Expenses
- Stock alerts (queue → Telegram, bila dikonfigurasi)
- Auth Owner/Admin (RBAC dasar)
- **Production Run Management** (batch production: yield, waste, cost snapshot)
- **Waste Tracking** (terpisah dari COGS)

### What's IN (MVP — berikutnya, P1):

- Partner management (customer + supplier terpadu)
- Partner aging (siapa belum bayar, umur piutang)
- Invoice tracking (daftar outstanding)
- Integrasi penuh bot Python ↔ API Laravel
- User settings (profil, role, password) — P2

### What's OUT (MVP):

- ❌ POS / receipt printing (Phase 3)
- ❌ Email parsing (Phase 3)
- ❌ Invoice generation / kirim invoice (Phase 3 — beda dengan *tracking* P1)
- ❌ B2B order management (Phase 3)
- ❌ Multi-user lanjutan / staff granular (Phase 2)

---

## 13. Beta Testing Strategy

### 13.1 First Beta User

**Target:** Temen Odi yang pakai POS competitor

**Deal:** Free access (beta testing)

**Goal:**
- Validasi apakah inventory + COGS + tax emulator emang solve pain point-nya
- Gather feedback
- Iterasi cepat

**Success Criteria:**
- User mau continue setelah beta
- User kasih feedback positif
- User rekomendasikan ke orang lain

### 13.2 Beta Phase

1. **Week 1-2:** Setup resep & gramasi
2. **Week 3-4:** Input transaksi harian via bot
3. **Week 5-6:** Review COGS & inventory alerts
4. **Week 7-8:** Tax simulator testing

---

## 14. Success Metrics

| Metric | Target |
|--------|--------|
| User can input transaction via bot | < 5 seconds |
| COGS calculated accurately | ± 5% of actual |
| Tax simulator shows realistic estimate | ± 10% of actual |
| Stock alerts work correctly | 100% accurate |
| Dashboard loads fast | < 2 seconds |
| Beta user continues after trial | > 80% |

---

## 15. Open Questions

1. **Logo/Brand:** Perlu desain brand identity?
2. **Bot personality:** Formal atau friendly?
3. **Language:** Bahasa Indonesia only atau bilingual?
4. **Support:** WhatsApp? Email? Telegram group?
5. **RBAC admin:** Konfirmasi target — admin hanya via bot, atau tetap akses web terbatas (inventory/transaksi)?

---

## 16. Next Steps

1. [x] Review & finalize PRD
2. [x] Buat Roles & Sequence Diagram
3. [x] Database schema design (domain inti: inventory, produk, resep, transaksi, penjualan)
4. [x] API spec & endpoint bot MVP (Sanctum)
5. [ ] Wireframe detail halaman Partner & Invoice
6. [x] Setup development environment (Sail + Postgres)
7. [x] Development dashboard MVP (v0.1.0)
8. [ ] Schema + API + UI: Partner, Aging, Invoice tracking
9. [ ] Wire bot Python ke API Laravel
10. [ ] Deploy beta version (VPS)
11. [ ] Onboard beta user (temen Odi)

---

*Document version: 0.5*
*Last updated: 19 June 2026*
*Author: Sena (AI Assistant)*
