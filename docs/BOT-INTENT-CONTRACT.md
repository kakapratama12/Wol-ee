# Bot API Contract — Intent System

> **Versi**: 1.0 | **Tanggal**: 2026-06-24
>
> Contract ini mendefinisikan semua intent yang bisa dihandle bot,
> termasuk required fields, validasi, dan endpoint Laravel API yang dipanggil.

---

## Arsitektur

```
User Input (text / foto)
    │
    ▼
┌─────────────────────┐
│  Intent Classifier  │  ← 1 API call (Gemini Flash Lite)
│  + Field Extractor  │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Field Validator     │  ← Local logic (gak perlu AI)
│  (required check)   │
└─────────┬───────────┘
          │
     ┌────┴────┐
     │ Complete?│
     └────┬────┘
      No  │  Yes
      │   │
      ▼   ▼
┌────────┐ ┌────────────┐
│ Ask    │ │ Confirm    │
│ Missing│ │ + Save     │
│ Field  │ │ (POST API) │
└────────┘ └────────────┘
```

---

## 1. Intent Definitions

### 1.1 `purchase` — Input Pembelian Bahan Baku

**Trigger examples:**
- "Beli gula 5kg 28rb"
- "Beli tepung terigu 10kg"
- Foto struk dari supplier

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `item_name` | string | Wajib, min 2 char | "gula pasir" |
| `quantity` | numeric | Wajib, > 0 | 5 |
| `unit_price` | numeric | Wajib, > 0 | 5600 |
| `total` | numeric | = quantity × unit_price (auto-hitung) | 28000 |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `supplier_name` | string | null | Nama supplier |
| `unit` | string | "pcs" | Satuan (kg, liter, pcs, dll) |
| `note` | string | null | Catatan |
| `occurred_at` | date | today | Tanggal transaksi |

**API Endpoint:** `POST /api/transactions`

**Request Body:**

```json
{
  "ingredient": "gula pasir",
  "quantity": 5,
  "unit_price": 5600,
  "note": null,
  "occurred_at": "2026-06-24"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Pembelian tercatat.",
  "data": {
    "id": 102,
    "ingredient": "gula pasir",
    "quantity": 5.0,
    "unit_price": 5600.0,
    "total": 28000.0,
    "new_stock": 15.0,
    "stock_status": "aman"
  }
}
```

**Error Handling:**

| Kondisi | Error Code | Action |
|---|---|---|
| Ingredient tidak ditemukan | `INGREDIENT_NOT_FOUND` | Tampilkan `available_items`, suruh pilih |
| Stok tidak cukup (untuk jual) | `VALIDATION_ERROR` | Tampilkan sisa stok |
| Harga 0 atau negatif | `VALIDATION_ERROR` | Minta user input ulang |

---

### 1.2 `sale` — Input Penjualan Produk

**Trigger examples:**
- "Jual matcha latte 10 gelas"
- "Klien Budi beli croissant 5"
- Foto struk penjualan

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `product_name` | string | Wajib, min 2 char | "matcha latte" |
| `quantity` | integer | Wajib, > 0 | 10 |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `unit_price` | numeric | null (ambil dari product) | Harga override |
| `customer_name` | string | null | Nama customer |
| `occurred_at` | date | today | Tanggal transaksi |

**API Endpoint:** `POST /api/sales`

**Request Body:**

```json
{
  "product": "matcha latte",
  "quantity": 10,
  "occurred_at": "2026-06-24"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Penjualan tercatat.",
  "data": {
    "id": 55,
    "product": "matcha latte",
    "quantity": 10,
    "revenue": 350000.0,
    "cogs": 120000.0,
    "profit": 230000.0,
    "margin": 65.71
  }
}
```

**Error Handling:**

| Kondisi | Error Code | Action |
|---|---|---|
| Product tidak ditemukan | `PRODUCT_NOT_FOUND` | Tampilkan `available_items` |
| Stok tidak cukup | `VALIDATION_ERROR` | Tampilkan sisa stok, suruh input pembelian dulu |

---

### 1.3 `expense` — Input Biaya/Operasional

**Trigger examples:**
- "Bayar listrik 500rb"
- "Bayar WiFi 300ribu"
- "Sewa tempat 5 juta"

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `description` | string | Wajib, min 2 char | "bayar listrik" |
| `amount` | numeric | Wajib, > 0 | 500000 |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `category` | string | "operasional" | `bahan_baku`, `operasional`, `overhead`, `di_luar_usaha` |
| `occurred_at` | date | today | Tanggal transaksi |

**Category Mapping:**

| User Input Keywords | Category |
|---|---|
| listrik, air, wifi, internet, sewa, gaji, karyawan | `operasional` |
| bahan baku, ingredient | `bahan_baku` |
| perawatan, service, repair | `overhead` |
| pribadi, rumah tangga, non-usaha | `di_luar_usaha` |

**API Endpoint:** `POST /api/expenses`

**Request Body:**

```json
{
  "description": "bayar listrik",
  "amount": 500000,
  "category": "operasional",
  "occurred_at": "2026-06-24"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Biaya tercatat.",
  "data": {
    "id": 23,
    "description": "bayar listrik",
    "amount": 500000.0,
    "category": "operasional"
  }
}
```

---

### 1.4 `invoice` — Buat Invoice ke Customer

**Trigger examples:**
- "Bikin invoice ke Pak Budi 500rb"
- "Tagih Pak Budi 500ribu"
- "Faktur ke PT Maju 2 juta"

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `partner_name` | string | Wajib | "Pak Budi" |
| `amount` | numeric | Wajib, > 0 | 500000 |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `due_date` | date | +30 hari | Jatuh tempo |
| `note` | string | null | Catatan |

**API Endpoint:** `POST /api/invoices`

**Request Body:**

```json
{
  "partner": "Pak Budi",
  "amount": 500000,
  "due_date": "2026-07-24",
  "note": null
}
```

**Response:**

```json
{
  "success": true,
  "message": "Invoice dibuat.",
  "data": {
    "id": 12,
    "invoice_number": "INV-2026-0012",
    "partner": "Pak Budi",
    "amount": 500000.0,
    "status": "outstanding",
    "due_date": "2026-07-24"
  }
}
```

---

### 1.5 `payment` — Catat Pembayaran Invoice

**Trigger examples:**
- "Pak Budi bayar 200rb"
- "Dapat pembayaran dari PT Maju 500ribu"
- "Lunas Pak Budi"

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `partner_name` | string | Wajib | "Pak Budi" |
| `amount` | numeric | Wajib, > 0 | 200000 |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `invoice_number` | string | null | Nomor invoice spesifik |

**API Endpoint:** `POST /api/invoices/{id}/payment`

**Flow:**
1. Cari invoice outstanding berdasarkan partner_name
2. Kalau cuma 1 → langsung bayar
3. Kalau ada beberapa → tampilkan list, suruh pilih

**Request Body:**

```json
{
  "amount": 200000,
  "paid_at": "2026-06-24"
}
```

---

### 1.6 `partner` — Tambah Partner

**Trigger examples:**
- "Tambah supplier PT Gula Manis"
- "Catat customer Pak Budi"
- "Partner baru: Ibu Sari, customer"

**Required Fields:**

| Field | Type | Validasi | Contoh |
|---|---|---|---|
| `name` | string | Wajib | "PT Gula Manis" |
| `type` | string | Wajib: `customer` atau `supplier` | "supplier" |

**Optional Fields:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `phone` | string | null | Nomor telepon |
| `email` | string | null | Email |
| `address` | string | null | Alamat |

**Type Detection Keywords:**

| Keywords | Type |
|---|---|
| supplier, pemasok, distributor, toko, vendor | `supplier` |
| customer, klien, pelanggan, pembeli | `customer` |
| (default) | `customer` |

**API Endpoint:** `POST /api/partners`

---

### 1.7 `query` — Tanya Informasi

**Trigger examples:**
- "Stok gula berapa?"
- "Laba bulan ini berapa?"
- "Siapa aja customer?"
- "Penjualan hari ini"

**Sub-intents:**

| Query Type | Trigger Keywords | API Endpoint |
|---|---|---|
| `stock` | stok, stock, sisa | `GET /api/stocks` |
| `report_pnl` | laba, profit, untung, rugi, omset | `GET /api/reports/pnl` |
| `report_sales` | penjualan, jual, revenue | `GET /api/reports/sales` |
| `top_products` | terlaris, best seller, top | `GET /api/reports/top-products` |
| `low_stock` | menipis, kritis, habis | `GET /api/stock-alerts` |
| `partners` | partner, customer, supplier | `GET /api/partners` |

**No required fields** — user langsung dapat jawaban.

---

## 2. Unified Intent Response Format

Semua intent menghasilkan JSON dengan format:

```json
{
  "intent": "purchase|sale|expense|invoice|payment|partner|query",
  "confidence": 0.0-1.0,
  "fields": {
    "item_name": "gula pasir",
    "quantity": 5,
    "unit_price": 5600,
    "total": 28000
  },
  "missing_required": [],
  "raw_input": "Beli gula 5kg 28rb"
}
```

---

## 3. Bot Response Flow

### 3.1 Complete Fields → Confirm

```
📸 Struk terbaca:

🏪 Supplier: PT Gula Manis
• Gula Pasir × 5 kg @ Rp 5,600 = Rp 28,000
💰 Total: Rp 28,000

[✅ Simpan] [✏️ Edit] [❌ Batal]
```

### 3.2 Missing Required → Ask

```
🤔 Mau catat pembelian, tapi ada yang kurang:

✅ Item: Gula Pasir
✅ Total: Rp 28,000
❌ Jumlah: ???

Berapa kilo/gram gulanya?
```

### 3.3 Ambiguous Intent → Clarify

```
🤔 Mau catat apa nih?

1️⃣ Pembelian bahan baku
2️⃣ Penjualan produk
3️⃣ Biaya operasional

Pilih nomor atau ketik sendiri.
```

### 3.4 Ingredient/Product Not Found

```
❌ "Gula Merah" gak ditemukan di inventory.

Bahan yang tersedia:
• Gula Pasir
• Gula Aren
• Gula Kristal

Pilih dari list, atau ketik nama yang benar.
```

---

## 4. Edit Flow

Saat user klik ✏️ Edit:

```
📝 Edit transaksi:

Saat ini:
• Item: Gula Pasir
• Jumlah: 5 kg
• Harga: Rp 5,600
• Total: Rp 28,000

Mau ubah apa? Ketik:
• "jumlahnya 10"
• "harganya 6000 per kg"
• "tanggalnya kemarin"
```

Bot update field yang disebutkan, tampilkan ulang preview, lalu minta confirm lagi.

---

## 5. OCR → Intent Mapping

Untuk foto struk, Gemini Vision menghasilkan structured JSON.
Mapping ke intent berdasarkan konteks:

| Konteks Struk | Intent | Indicator |
|---|---|---|
| Ada nama supplier, item bahan baku | `purchase` | Supplier name present |
| Ada nama customer, produk jadi | `sale` | Customer name present |
| Total saja, gak ada partner | `expense` | No partner info |

**Enhanced OCR Prompt:**

```
Ekstrak struk ini ke JSON:
{
  "intent": "purchase atau sale",
  "partner_name": "nama supplier atau customer",
  "items": [{"name": "...", "qty": ..., "price": ..., "total": ...}],
  "total": ...,
  "date": "...",
  "payment_method": "..."
}

Tentukan intent berdasarkan:
- Kalau ada nama supplier/distributor → purchase
- Kalau ada nama customer/pembeli → sale
- Kalau hanya total tanpa partner → expense
```

---

## 6. Error Responses

| Error | Bot Response |
|---|---|
| API timeout | "⏳ Server lagi sibuk, coba lagi dalam beberapa detik." |
| Network error | "🌐 Gak ada koneksi. Coba lagi nanti." |
| Invalid data | "❌ Data gak valid: [detail]. Coba input ulang." |
| Unknown intent | "🤔 Gak yakin ini jenis transaksi apa. Coba jelaskan lebih detail." |
| Rate limit | "⚠️ Terlalu banyak request. Tunggu sebentar." |

---

## 7. Implementation Notes

### AI Call Efficiency

| Step | AI Call? | Model | Cost |
|---|---|---|---|
| Intent + Extract | Yes (1 call) | Gemini Flash Lite | ~Rp 3-5 |
| Validate | No | Local logic | $0 |
| Ask missing field | No | Local template | $0 |
| Confirm + Save | No | HTTP POST | $0 |

**Total per transaksi: 1 AI call = Rp 3-5**

### Rate Limiting

| Tier | Limit | Reset |
|---|---|---|
| Free | 10 foto/bulan, 20 pesan/hari | Bulanan/Harian |
| Pro | 100 foto/bulan, 200 pesan/hari | Bulanan |

### Conversation Context

Bot menyimpan konteks percakapan terakhir:
- Intent yang sedang di-handle
- Fields yang sudah terisi
- Menunggu input dari user

Context expired setelah 5 menit tanpa aktivitas.
