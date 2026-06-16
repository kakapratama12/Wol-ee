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
| **Transaction List** | Daftar semua transaksi | P0 |
| **Inventory Management** | Tabel stok dengan status (gramasi + packaged) | P0 |
| **Recipe Management** | Setup resep + gramasi per produk | P0 |
| **COGS Calculator** | Hitung COGS per produk otomatis | P0 |
| **P&L Report** | Profit & Loss, export ke Excel | P0 |
| **Tax Simulator** | Estimasi pajak berdasarkan input | P0 |
| **Margin Protection** | Price tracker, margin alert, what-if simulator | P0 |

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

### 6.4 Flow: P&L Report (Dashboard)

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

### 9.1 Unit Types

**Bahan Baku (Gramasi):**
- Tepung: kg
- Susu: liter
- Telur: butir
- Gula: gram
- Kopi: kg

**Bahan Baku (Packaged):**
- Coklat bubuk: sachet
- Pasta matcha: sachet
- Sirup: botol

**Produk Jadi (PCS):**
- Matcha Latte: biji/pc
- Croissant: pcs
- Kopi Susu: cup
- Roti Goreng: pcs

### 9.2 Stock Flow

```
Beli Tepung 5kg (Rp 100.000)
    ↓
Stok Tepung: 5kg
    ↓
Jual Matcha 10 biji
    ↓
Estimasi terpakai: 1kg (100g × 10)
    ↓
Stok Tepung: 4kg
```

### 9.3 Alert System

| Status | Kondisi | Action |
|--------|---------|--------|
| ✅ Aman | Stok > minimum | - |
| ⚠️ Menipis | Stok < minimum | Alert ke bot |
| 🔴 Kritis | Stok < 50% minimum | Urgent alert |

---

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

### What's IN (MVP):
- ✅ Transaction input via bot (NL)
- ✅ Sales input via bot (NL)
- ✅ Stock tracking (gramasi + packaged)
- ✅ Recipe management
- ✅ COGS calculation
- ✅ Dashboard overview
- ✅ Inventory alerts
- ✅ Tax simulator
- ✅ P&L report + export Excel
- ✅ Margin protection (price tracker, margin alert, what-if simulator)

### What's OUT (MVP):
- ❌ POS/Receipt printing (Phase 3)
- ❌ Email parsing (Phase 3)
- ❌ Multi-user (Phase 2)
- ❌ Invoice generation (Phase 3)
- ❌ B2B order management (Phase 3)

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
5. **Timeline:** Kapan mau mulai development?

---

## 16. Next Steps

1. [x] Review & finalize PRD
2. [ ] Buat Roles & Sequence Diagram
3. [ ] Database schema design
4. [ ] API spec (endpoints)
5. [ ] Wireframe detail per page
6. [ ] Setup development environment
7. [ ] Mulai development (agent)
8. [ ] Deploy beta version
9. [ ] Onboard beta user

---

*Document version: 0.3*
*Last updated: 16 June 2026*
*Author: Sena (AI Assistant)*
