# Wol-ee: Roles & Sequence Diagram

## 1. User Roles Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      WOL-EE USERS                           │
├─────────────────────────┬───────────────────────────────────┤
│      OWNER              │         ADMIN/STAFF               │
├─────────────────────────┼───────────────────────────────────┤
│ ✅ Full access           │ ⚠️ Limited access                 │
│ ✅ Dashboard             │ ✅ Bot (input/output)             │
│ ✅ Tax simulator         │ ✅ Inventory view                 │
│ ✅ P&L reports           │ ❌ Tax simulator                  │
│ ✅ Setup resep           │ ❌ P&L reports                    │
│ ✅ Multi-user management │ ❌ Setup resep                    │
└─────────────────────────┴───────────────────────────────────┘
```

---

## 2. Role: Owner

### 2.1 Capabilities

| Area | Can Do | Via |
|------|--------|-----|
| **Transaction** | Input, view, edit, delete | Bot + Dashboard |
| **Inventory** | View, setup resep, adjust stok | Dashboard |
| **COGS** | View, calculate, compare | Dashboard |
| **Tax** | Simulator, view estimasi | Dashboard |
| **Reports** | P&L, cash flow, export Excel | Dashboard |
| **Multi-user** | Add/manage admin/staff | Dashboard |

### 2.2 Owner Interactions

**Via Bot (Telegram):**
```
Owner → "Profit hari ini berapa?"
Bot   → "Profit hari ini: Rp 1.44M (margin 60%)"

Owner → "Stok semua"
Bot   → "Stok aman semua. Susu menipis (2L), minimum 5L."
```

**Via Dashboard (Web):**
```
Owner → Login → Lihat dashboard overview
Owner → Klik "Inventory" → Lihat tabel stok
Owner → Klik "Resep" → Setup/edit resep
Owner → Klik "Tax Simulator" → Estimasi pajak
Owner → Klik "P&L" → Lihat laporan, export Excel
```

---

## 3. Role: Admin/Staff

### 3.1 Capabilities

| Area | Can Do | Via |
|------|--------|-----|
| **Transaction** | Input, view own | Bot |
| **Inventory** | View stok, input pembelian | Bot |
| **Sales** | Input penjualan | Bot |
| **COGS** | View estimate per penjualan | Bot |
| **Reports** | View daily summary | Bot |
| **Tax** | ❌ No access | - |
| **P&L** | ❌ No access | - |
| **Setup** | ❌ No access | - |

### 3.2 Admin Interactions

**Via Bot (Telegram):**
```
Admin → "Beli tepung 2kg 40 ribu"
Bot   → "✅ Tercatat! Stok tepung: 5kg"

Admin → "Jual matcha 10 biji"
Bot   → "✅ Terjual! COGS estimate: Rp 85K"

Admin → "Stok hari ini"
Bot   → "📦 Tepung: 5kg ✅ | Susu: 2L ⚠️ | Telur: 100 ✅"
```

---

## 4. Sequence Diagrams

### 4.1 Transaction Input (Bot → Database)

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ Admin │     │  Bot    │     │   API   │     │ DB  │
│       │     │(Telegram)│    │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ "Beli tepung │               │              │
    │  2kg 40k"    │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Parse NL      │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Validate     │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │ Update stok  │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │               │              │
    │              │◀──────────────│              │
    │              │               │              │
    │  ✅ Tercatat!│               │              │
    │  Stok: 5kg  │               │              │
    │◀─────────────│               │              │
    │              │               │              │
```

**What happens:**
1. Admin kirim pesan ke bot
2. Bot parse natural language → extract: item, qty, price
3. Bot kirim ke API (Laravel)
4. API validate data
5. API update database (stok增加)
6. API return response ke bot
7. Bot kirim konfirmasi ke admin

---

### 4.2 Sales Input + COGS Calculation

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐     ┌─────────┐
│ Admin │     │  Bot    │     │   API   │     │ DB  │     │ COGS    │
│       │     │(Telegram)│    │(Laravel)│     │     │     │ Engine  │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘     └────┬────┘
    │              │               │              │              │
    │ "Jual matcha │               │              │              │
    │  10 biji"    │               │              │              │
    │─────────────▶│               │              │              │
    │              │               │              │              │
    │              │ Parse NL      │              │              │
    │              │──────────────▶│              │              │
    │              │               │              │              │
    │              │               │ Get resep    │              │
    │              │               │─────────────▶│              │
    │              │               │              │              │
    │              │               │◀─────────────│              │
    │              │               │              │              │
    │              │               │ Hitung COGS  │              │
    │              │               │─────────────────────────────▶
    │              │               │              │              │
    │              │               │◀─────────────────────────────│
    │              │               │              │              │
    │              │               │ Update stok  │              │
    │              │               │─────────────▶│              │
    │              │               │              │              │
    │              │               │ Record transaksi             │
    │              │               │─────────────▶│              │
    │              │               │              │              │
    │              │               │◀─────────────│              │
    │              │◀──────────────│              │              │
    │              │               │              │              │
    │  ✅ Terjual! │               │              │              │
    │  COGS: 85K  │               │              │              │
    │  Profit: 365K              │              │              │
    │◀─────────────│               │              │              │
```

**What happens:**
1. Admin kirim: "Jual matcha 10 biji"
2. Bot parse → extract: produk, qty
3. API get resep matcha dari database
4. API hitung COGS (gramasi × harga bahan × qty)
5. API update stok (kurangi bahan terpakai)
6. API record transaksi (revenue + COGS)
7. Bot return: terjual, COGS, profit estimate

---

### 4.3 Stock Check

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ User  │     │  Bot    │     │   API   │     │ DB  │
│       │     │(Telegram)│    │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ "Stok hari  │               │              │
    │  ini"        │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get all stok  │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Query stok   │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │              │ Format response               │
    │              │               │              │
    │  📦 Tepung:  │               │              │
    │  5kg ✅      │               │              │
    │  Susu: 2L ⚠️ │               │              │
    │◀─────────────│               │              │
```

---

### 4.4 Tax Simulator (Owner via Dashboard)

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ Owner │     │Dashboard│     │   API   │     │ DB  │
│       │     │  (Web)  │     │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ Klik "Tax   │               │              │
    │ Simulator"   │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get COGS data │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Query COGS   │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │ Input:       │               │              │
    │ - Tipe: CV   │               │              │
    │ - Omset: 72M │               │              │
    │ - Expense: 15M              │              │
    │ - Waste: 15% │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Hitung pajak  │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │  ┌─────────────────────────────────────┐    │
    │  │ PP 23: 360K                         │    │
    │  │ Normal: 1.19M                       │    │
    │  │ Selisih: 830K                       │    │
    │  └─────────────────────────────────────┘    │
```

---

### 4.5 P&L Report + Export (Owner via Dashboard)

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ Owner │     │Dashboard│     │   API   │     │ DB  │
│       │     │  (Web)  │     │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ Klik "P&L"  │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get data      │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Query transaksi
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │ Query COGS   │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │  ┌─────────────────────────────────────┐    │
    │  │ P&L Report - Juni 2026             │    │
    │  │ Revenue: 72M                       │    │
    │  │ COGS: 28.8M                        │    │
    │  │ Profit: 43.2M                      │    │
    │  │ Expenses: 15M                      │    │
    │  │ Laba Bersih: 28.2M                 │    │
    │  └─────────────────────────────────────┘    │
    │              │               │              │
    │ Klik "Export │               │              │
    │  Excel"      │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Generate Excel│              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │ [Download    │               │              │
    │  file.xlsx]  │               │              │
```

---

### 4.6 Recipe Setup (Owner via Dashboard)

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ Owner │     │Dashboard│     │   API   │     │ DB  │
│       │     │  (Web)  │     │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ Klik "Resep" │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get list resep│              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Query resep  │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │ Klik "Tambah │               │              │
    │  Resep"      │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │ Isi form:    │               │              │
    │ - Nama: Matcha              │              │
    │ - Susu: 200ml               │              │
    │ - Matcha: 20g               │              │
    │ - Gula: 15g                 │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Save resep    │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Insert resep │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │              │ Hitung COGS   │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Get harga    │
    │              │               │ bahan        │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │  Resep tersimpan:           │              │
    │  COGS/porsi: Rp 8.500      │              │
    │  Margin: 81% │               │              │
```

### 4.7 Margin Protection (Owner via Dashboard)

```
┌───────┐     ┌─────────┐     ┌─────────┐     ┌─────┐
│ Owner │     │Dashboard│     │   API   │     │ DB  │
│       │     │  (Web)  │     │(Laravel)│     │     │
└───┬───┘     └────┬────┘     └────┬────┘     └──┬──┘
    │              │               │              │
    │ Klik "Margin │               │              │
    │ Protection"  │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get margin    │              │
    │              │ data          │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │ Query harga  │
    │              │               │ historis     │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │ Query COGS   │
    │              │               │─────────────▶│
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │  ┌─────────────────────────────────────┐    │
    │  │ Margin Alert:                      │    │
    │  │ ⚠️ Matcha Latte: margin turun 2%   │    │
    │  │ Penyebab: harga tepung naik 22%    │    │
    │  └─────────────────────────────────────┘    │
    │              │               │              │
    │ Klik "Lihat  │               │              │
    │  Detail"     │               │              │
    │─────────────▶│               │              │
    │              │               │              │
    │              │ Get detail    │              │
    │              │──────────────▶│              │
    │              │               │              │
    │              │               │◀─────────────│
    │              │◀──────────────│              │
    │              │               │              │
    │◀─────────────│               │              │
    │              │               │              │
    │  ┌─────────────────────────────────────┐    │
    │  │ Price History:                     │    │
    │  │ Jan: 18K → Jul: 22K (+22%)        │    │
    │  │                                     │    │
    │  │ What-If:                           │    │
    │  │ Kalau naik 10% lagi: margin 77%    │    │
    │  │ Kalau mau margin 81%: harga 47.2K  │    │
    │  └─────────────────────────────────────┘    │
```

---

## 5. Role Permissions Matrix

| Feature | Owner | Admin/Staff |
|---------|-------|-------------|
| **Bot: Input transaksi** | ✅ | ✅ |
| **Bot: Input penjualan** | ✅ | ✅ |
| **Bot: Cek stok** | ✅ | ✅ |
| **Bot: Cek partner aging** | ✅ | ⚠️ Limit |
| **Bot: Follow-up customer** | ✅ | ❌ |
| **Dashboard: Overview & operasional** | ✅ | ⚠️ Lihat catatan |
| **Dashboard: Partner & invoice** | ✅ | ❌ |
| **Dashboard: Resep, P&L, Tax, Margin** | ✅ | ❌ |
| **Dashboard: Edit/hapus master data** | ✅ | ❌ |
| **Dashboard: Multi-user** | ✅ | ❌ |

> **Catatan implementasi (MVP v0.1.0):** Role `admin` saat ini masih bisa akses web untuk
> inventory, transaksi, dan penjualan (operasional harian). Baris "operasional" di atas
> = target setelah RBAC diselaraskan; owner-only pages (produk, pajak, P&L, margin) sudah
> sesuai target.

---

## 6. Bot Response Templates

### 6.1 Transaction Confirmation
```
✅ Tercatat!
📦 [Item]: [Stok baru] ([+/- qty])
```

### 6.2 Sales Confirmation
```
✅ Terjual!
🔻 Estimasi terpakai:
   [Bahan]: [Qty]
💰 Revenue: Rp [amount]
📉 COGS: Rp [amount]
📈 Profit: Rp [amount] ([margin]%)
⚠️ [Alert jika ada]
```

### 6.3 Stock Status
```
📦 Stok Hari Ini:
[Item]: [Qty] [Status emoji]
[Item]: [Qty] [Status emoji]

Status: ✅ Aman | ⚠️ Menipis | 🔴 Kritis
```

---

*Document version: 0.4*
*Last updated: 17 June 2026*
*Author: Sena (AI Assistant)*
