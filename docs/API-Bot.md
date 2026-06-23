# API Bot — Dokumentasi Lengkap

> **Versi**: 1.0 | **Terakhir diperbarui**: 2026-06-20
>
> Dokumentasi ini ditujukan untuk developer bot Telegram (Python). Seluruh deskripsi dalam Bahasa Indonesia, field/code dalam Bahasa Inggris.

---

## Daftar Isi

1. [Overview](#1-overview)
2. [Autentikasi](#2-autentikasi)
3. [Standard Response Format](#3-standard-response-format)
4. [Endpoint yang Sudah Ada](#4-endpoint-yang-sudah-ada)
   - [4.1 Bot (Auth, Usage, AI)](#41-bot-auth-usage-ai)
   - [4.2 Transaksi / Pembelian](#42-transaksi--pembelian)
   - [4.3 Penjualan](#43-penjualan)
   - [4.4 Biaya / Expense](#44-biaya--expense)
   - [4.5 Produk](#45-produk)
   - [4.6 Stok](#46-stok)
   - [4.7 Laporan / Reports](#47-laporan--reports)
   - [4.8 Partner](#48-partner)
   - [4.9 Invoice](#49-invoice)
   - [4.10 Production Runs](#410-production-runs)
5. [Endpoint yang Perlu Dibangun (Missing)](#5-endpoint-yang-perlu-dibangun-missing)
6. [Data Models — Referensi Cepat](#6-data-models--referensi-cepat)
7. [Error Responses](#7-error-responses)

---

## 1. Overview

| Item | Keterangan |
|---|---|
| **Base URL** | `https://{app_url}/api` (production), `http://localhost/api` (lokal) |
| **Protocol** | HTTPS (production) |
| **Format** | JSON |
| **Auth** | Bearer token (custom token, bukan Sanctum personal access token) |
| **Rate Limit** | `throttle:bot` — default 60 request/menit per IP |
| **Tenant Isolation** | Semua data otomatis di-scoping ke tenant pemilik token |

---

## 2. Autentikasi

Bot menggunakan **custom Bearer token** yang terdiri dari `{tenant_id}:{secret}`. Token di-hash di database.

### Alur Autentikasi

1. **Validate token** dulu via `POST /api/bot/validate-token` untuk memastikan token valid dan mendapat info tenant.
2. Setelah valid, gunakan token yang sama sebagai Bearer token di header untuk semua endpoint lain.

### Header yang Diperlukan

```
Authorization: Bearer {tenant_id}:{secret}
Content-Type: application/json
```

### Bagaimana Middleware Bekerja

1. Middleware `BotTokenAuth` mengekstrak Bearer token dari header.
2. Token di-split: `[tenantId, secret]`.
3. `secret` di-hash dan dicocokkan dengan `bot_token` di tabel `tenants`.
4. Jika valid, `tenant` di-attach ke request (`$request->attributes->get('tenant')`) dan owner tenant di-login ke `auth()`.

### Token Format

```
{tenant_id}:{random_32_chars}
# Contoh: 1:aB3dE5gH7iJ9kL1mN3oP5qR7sT9uV
```

> ⚠️ Token hanya ditampilkan **satu kali** saat generate. Simpan di tempat aman.

---

## 3. Standard Response Format

### Sukses

```json
{
  "success": true,
  "message": "Pesan sukses (Bahasa Indonesia)",
  "data": { ... }
}
```

`data` bisa berupa object atau array. Beberapa endpoint menggunakan `data` wrapper (Partner, Invoice), beberapa langsung mengembalikan array di `data`.

### Error

```json
{
  "success": false,
  "message": "Pesan error (Bahasa Indonesia)",
  "error_code": "ERROR_CODE_SNAKE_CASE"
}
```

Error dapat memiliki field tambahan (`extra`) seperti `available_items`, `dashboard_url`, `errors`, dll.

---

## 4. Endpoint yang Sudah Ada

Semua endpoint di bawah ini memerlukan header `Authorization: Bearer {token}` kecuali yang ditandai.

---

### 4.1 Bot (Auth, Usage, AI)

#### POST `/api/bot/validate-token`

> 🔓 **Tidak perlu Bearer token** — endpoint ini sendiri yang mengecek token.

**Request:**

```json
{
  "token": "1:aB3dE5gH7iJ9kL1mN3oP5qR7sT9uV"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `token` | string | ✅ | Token mentah `{tenant_id}:{secret}` |

**Response 200:**

```json
{
  "success": true,
  "message": "Token valid.",
  "data": {
    "tenant": {
      "id": 1,
      "name": "Kafe Kopi Enak",
      "plan": "free"
    }
  }
}
```

**Response 401:**

```json
{
  "success": false,
  "message": "Token tidak valid.",
  "error_code": "UNAUTHORIZED"
}
```

---

#### GET `/api/bot/usage`

Cek kuota AI harian untuk user Telegram tertentu.

**Query Parameters:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `telegram_user_id` | integer | ✅ | ID user Telegram |

**Response 200:**

```json
{
  "success": true,
  "message": "Kuota AI bot.",
  "data": {
    "used": 3,
    "limit": 10,
    "remaining": 7
  }
}
```

---

#### POST `/api/bot/ai-usage`

Konsumsi 1 kuota AI (dipanggil sebelum bot memproses permintaan AI).

**Request:**

```json
{
  "telegram_user_id": 123456789
}
```

**Response 200 (kuota tersedia):**

```json
{
  "success": true,
  "message": "Kuota AI digunakan.",
  "data": {
    "consumed": true,
    "used": 4,
    "limit": 10,
    "remaining": 6
  }
}
```

**Response 429 (kuota habis):**

```json
{
  "success": false,
  "message": "Kuota AI hari ini habis. Reset besok jam 00:00 WIB. Upgrade ke Pro untuk kuota lebih besar.",
  "error_code": "AI_QUOTA_EXCEEDED",
  "used": 10,
  "limit": 10,
  "remaining": 0
}
```

---

#### POST `/api/bot/ai-requests`

Catat log request AI (telemetry). Dipanggil setelah bot selesai memproses.

**Request:**

```json
{
  "telegram_user_id": 123456789,
  "plan": "free",
  "provider": "openai",
  "model": "gpt-4o-mini",
  "status": "success",
  "error_code": null,
  "latency_ms": 1250,
  "prompt_tokens": 350,
  "completion_tokens": 120,
  "total_tokens": 470,
  "requested_at": "2026-06-20T10:30:00Z"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `telegram_user_id` | integer | ✅ | ID user Telegram |
| `plan` | string | ✅ | Plan user: `free`, `pro`, dll (harus sesuai `config('ai.plans')`) |
| `provider` | string | ✅ | Provider AI: `openai`, `anthropic`, dll (harus sesuai `config('ai.providers')`) |
| `model` | string | ❌ | Nama model (misal `gpt-4o-mini`) |
| `status` | string | ✅ | `success`, `error`, `quota_exceeded` |
| `error_code` | string | ❌ | Kode error jika gagal |
| `latency_ms` | integer | ❌ | Waktu respons dalam milidetik |
| `prompt_tokens` | integer | ❌ | Jumlah token prompt |
| `completion_tokens` | integer | ❌ | Jumlah token completion |
| `total_tokens` | integer | ❌ | Total token |
| `requested_at` | datetime | ❌ | Waktu request (ISO 8601). Default: `now()` |

**Response 201:**

```json
{
  "success": true,
  "message": "AI request dicatat.",
  "data": {
    "id": 42
  }
}
```

---

#### POST `/api/bot/feedback`

Catat feedback dari user Telegram.

**Request:**

```json
{
  "telegram_user_id": 123456789,
  "feedback_text": "Bot sangat membantu! Tapi response-nya agak lambat.",
  "original_message": "Berapa omset hari ini?"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `telegram_user_id` | integer | ✅ | ID user Telegram |
| `feedback_text` | string | ✅ | Teks feedback (3-2000 karakter) |
| `original_message` | string | ❌ | Pesan asli user (maks 2000 karakter) |

**Response 201:**

```json
{
  "success": true,
  "message": "Feedback dicatat.",
  "data": {
    "id": 5,
    "status": "new"
  }
}
```

---

### 4.2 Transaksi / Pembelian

#### GET `/api/transactions`

Daftar riwayat pembelian bahan baku.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `date` | string | — | Filter tanggal spesifik (format `Y-m-d`) |
| `limit` | integer | `10` | Jumlah baris (maks 50) |

**Response 200:**

```json
{
  "success": true,
  "message": "Riwayat pembelian.",
  "data": [
    {
      "id": 101,
      "ingredient": "kopi arabika",
      "base_unit": "gram",
      "quantity": 5000.0,
      "unit_price": 0.85,
      "total": 4250.0,
      "source": "bot",
      "note": "Stok dari supplier A",
      "occurred_at": "2026-06-20T08:00:00+07:00"
    }
  ]
}
```

---

#### POST `/api/transactions`

Catat satu pembelian bahan baku.

**Request:**

```json
{
  "ingredient": "kopi arabika",
  "quantity": 2000,
  "unit_price": 0.90,
  "note": "Restok mingguan",
  "occurred_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `ingredient_id` | integer | ❌* | ID bahan (salah satu dari `ingredient_id` atau `ingredient` wajib) |
| `ingredient` | string | ❌* | Nama bahan (case-insensitive, fuzzy). Salah satu wajib |
| `quantity` | numeric | ✅ | Kuantitas dalam base unit (gram, ml, dll). Harus > 0 |
| `unit_price` | numeric | ❌* | Harga per unit. Salah satu dari `unit_price` atau `total` wajib |
| `total` | numeric | ❌* | Total harga. Akan dihitung: `total / quantity` jika `unit_price` kosong |
| `note` | string | ❌ | Catatan (maks 255 karakter) |
| `occurred_at` | date | ❌ | Tanggal transaksi. Default: `now()` |

**Response 201:**

```json
{
  "success": true,
  "message": "Pembelian tercatat.",
  "data": {
    "id": 102,
    "ingredient": "kopi arabika",
    "quantity": 2000.0,
    "unit_price": 0.90,
    "total": 1800.0,
    "new_stock": 7000.0,
    "stock_status": "aman"
  }
}
```

**Response 422 (bahan tidak ditemukan):**

```json
{
  "success": false,
  "message": "Bahan 'kopi robusta' tidak ditemukan.",
  "error_code": "INGREDIENT_NOT_FOUND",
  "available_items": ["kopi arabika", "gula pasir", "susu uht", "..."],
  "dashboard_url": "https://app.example.com/inventory"
}
```

> 💡 `available_items` berisi daftar nama bahan yang tersedia (maks 50) untuk memudahkan bot melakukan fuzzy match atau menampilkan rekomendasi.

---

#### POST `/api/transactions/batch`

Catat beberapa pembelian sekaligus.

**Request:**

```json
{
  "items": [
    {
      "ingredient": "kopi arabika",
      "quantity": 2000,
      "unit_price": 0.90
    },
    {
      "ingredient": "gula pasir",
      "quantity": 5000,
      "total": 50000
    }
  ],
  "note": "Restok bulanan",
  "occurred_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `items` | array | ✅ | Array item (1-50 item) |
| `items.*.ingredient_id` | integer | ❌* | ID bahan per item |
| `items.*.ingredient` | string | ❌* | Nama bahan per item |
| `items.*.quantity` | numeric | ✅ | Kuantitas per item |
| `items.*.unit_price` | numeric | ❌* | Harga per unit per item |
| `items.*.total` | numeric | ❌* | Total per item |
| `note` | string | ❌ | Catatan untuk seluruh batch |
| `occurred_at` | date | ❌ | Tanggal untuk seluruh batch |

**Response 201:**

```json
{
  "success": true,
  "message": "Batch pembelian tercatat.",
  "data": {
    "transactions": [
      {
        "id": 103,
        "ingredient": "kopi arabika",
        "quantity": 2000.0,
        "total": 1800.0,
        "new_stock": 7000.0
      },
      {
        "id": 104,
        "ingredient": "gula pasir",
        "quantity": 5000.0,
        "total": 50000.0,
        "new_stock": 10000.0
      }
    ],
    "total_amount": 51800.0
  }
}
```

**Response 422 (beberapa bahan tidak ditemukan):**

```json
{
  "success": false,
  "message": "Beberapa bahan tidak ditemukan.",
  "error_code": "BATCH_VALIDATION_FAILED",
  "errors": [
    {
      "index": 0,
      "ingredient": "kopi robusta",
      "error_code": "INGREDIENT_NOT_FOUND",
      "message": "Bahan 'kopi robusta' tidak ditemukan."
    }
  ],
  "available_items": ["kopi arabika", "gula pasir", "..."],
  "dashboard_url": "https://app.example.com/inventory"
}
```

---

### 4.3 Penjualan

#### GET `/api/sales`

Daftar riwayat penjualan.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `date` | string | — | Filter tanggal (format `Y-m-d`) |
| `limit` | integer | `10` | Jumlah baris (maks 50) |

**Response 200:**

```json
{
  "success": true,
  "message": "Riwayat penjualan.",
  "data": [
    {
      "id": 201,
      "product": "Matcha Latte",
      "quantity": 3,
      "unit_price": 35000.0,
      "revenue": 105000.0,
      "cogs": 22500.0,
      "profit": 82500.0,
      "margin": 78.57,
      "source": "bot",
      "note": null,
      "occurred_at": "2026-06-20T09:30:00+07:00"
    }
  ]
}
```

---

#### POST `/api/sales`

Catat satu penjualan.

**Request:**

```json
{
  "product": "Matcha Latte",
  "quantity": 2,
  "unit_price": 35000,
  "note": "Pesanan grab",
  "occurred_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `product_id` | integer | ❌* | ID produk. Salah satu dari `product_id` atau `product` wajib |
| `product` | string | ❌* | Nama produk (case-insensitive) |
| `quantity` | integer | ✅ | Jumlah terjual. Harus > 0 |
| `unit_price` | numeric | ❌ | Harga jual per unit. Default: `selling_price` dari katalog |
| `total` | numeric | ❌ | Total harga. Jika diisi, `unit_price = total / quantity` |
| `note` | string | ❌ | Catatan (maks 255 karakter) |
| `occurred_at` | date | ❌ | Tanggal penjualan. Default: `now()` |

**Response 201:**

```json
{
  "success": true,
  "message": "Penjualan tercatat.",
  "data": {
    "id": 202,
    "product": "Matcha Latte",
    "quantity": 2,
    "unit_price": 35000.0,
    "catalog_unit_price": 35000.0,
    "revenue": 70000.0,
    "cogs": 15000.0,
    "profit": 55000.0,
    "margin": 78.57,
    "alerts": [
      {
        "ingredient": "matcha powder",
        "current": 50.0,
        "minimum": 100.0,
        "unit": "gram",
        "status": "menipis"
      }
    ]
  }
}
```

> 💡 Field `alerts` berisi daftar bahan yang stoknya menipis/kritis — muncul otomatis setelah penjualan karena stok berkurang. Gunakan untuk memberi notifikasi ke user.

---

#### POST `/api/sales/batch`

Catat beberapa penjualan sekaligus.

**Request:**

```json
{
  "items": [
    {
      "product": "Matcha Latte",
      "quantity": 3
    },
    {
      "product_id": 5,
      "quantity": 2,
      "unit_price": 28000
    }
  ],
  "note": "Penjualan pagi",
  "occurred_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `items` | array | ✅ | Array item (1-50 item) |
| `items.*.product_id` | integer | ❌* | ID produk per item |
| `items.*.product` | string | ❌* | Nama produk per item |
| `items.*.quantity` | integer | ✅ | Jumlah terjual per item |
| `items.*.unit_price` | numeric | ❌ | Harga per unit per item |
| `note` | string | ❌ | Catatan untuk seluruh batch |
| `occurred_at` | date | ❌ | Tanggal untuk seluruh batch |

**Response 201:**

```json
{
  "success": true,
  "message": "Batch penjualan tercatat.",
  "data": {
    "sales": [
      {
        "id": 203,
        "product": "Matcha Latte",
        "quantity": 3,
        "revenue": 105000.0,
        "profit": 82500.0
      },
      {
        "id": 204,
        "product": "Croissant",
        "quantity": 2,
        "revenue": 56000.0,
        "profit": 28000.0
      }
    ],
    "total_revenue": 161000.0,
    "total_profit": 110500.0,
    "alerts": []
  }
}
```

---

### 4.4 Biaya / Expense

#### POST `/api/expenses`

Catat biaya operasional/bulanan.

**Request:**

```json
{
  "category": "operasional",
  "description": "Listrik bulan Juni",
  "amount": 750000,
  "period_month": 6,
  "period_year": 2026
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `category` | string | ✅ | Kategori: `bahan_baku`, `operasional`, `overhead`, `non_operasional` |
| `description` | string | ❌ | Deskripsi (maks 255 karakter) |
| `amount` | numeric | ✅ | Nominal dalam Rupiah. Harus > 0 |
| `period_month` | integer | ✅ | Bulan (1-12) |
| `period_year` | integer | ✅ | Tahun (2000-2100) |

**Kategori Expense:**

| Kode | Label | Masuk P&L? |
|---|---|---|
| `bahan_baku` | Bahan Baku | ✅ |
| `operasional` | Operasional | ✅ |
| `overhead` | Overhead | ✅ |
| `non_operasional` | Di Luar Usaha | ❌ |

> ⚠️ `non_operasional` **tidak** masuk hitungan P&L, tapi masuk hitungan Cashflow.

**Response 201:**

```json
{
  "success": true,
  "message": "Biaya dicatat.",
  "data": {
    "id": 30,
    "category": "operasional",
    "description": "Listrik bulan Juni",
    "amount": 750000.0,
    "period_month": 6,
    "period_year": 2026
  }
}
```

---

### 4.5 Produk

#### GET `/api/products`

Daftar semua produk aktif.

**Query Parameters:** Tidak ada.

**Response 200:**

```json
{
  "success": true,
  "message": "Daftar produk.",
  "data": [
    {
      "id": 1,
      "name": "Matcha Latte",
      "unit": "pcs",
      "selling_price": 35000.0
    },
    {
      "id": 2,
      "name": "Croissant",
      "unit": "pcs",
      "selling_price": 18000.0
    }
  ]
}
```

> 💅 Hanya produk `is_active = true` yang ditampilkan.

---

### 4.6 Stok

#### GET `/api/stock`

Daftar stok semua bahan (ingredient).

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `ingredient` | string | — | Filter nama bahan (case-insensitive LIKE) |

**Response 200:**

```json
{
  "success": true,
  "message": "Daftar stok.",
  "data": [
    {
      "id": 1,
      "ingredient": "kopi arabika",
      "current_stock": 3500.0,
      "minimum_stock": 1000.0,
      "unit": "gram",
      "status": "aman"
    },
    {
      "id": 2,
      "ingredient": "susu uht",
      "current_stock": 2.0,
      "minimum_stock": 5.0,
      "unit": "liter",
      "status": "menipis"
    },
    {
      "id": 3,
      "ingredient": "matcha powder",
      "current_stock": 15.0,
      "minimum_stock": 100.0,
      "unit": "gram",
      "status": "kritis"
    }
  ]
}
```

**Status Stok:**

| Kode | Kondisi |
|---|---|
| `aman` | `current_stock > minimum_stock` |
| `menipis` | `current_stock <= minimum_stock` dan `>= 50% minimum_stock` |
| `kritis` | `current_stock < 50% minimum_stock` |

---

### 4.7 Laporan / Reports

#### GET `/api/reports/today`

Ringkasan penjualan hari ini.

**Response 200:**

```json
{
  "success": true,
  "message": "Laporan hari ini.",
  "data": {
    "date": "2026-06-20",
    "revenue": 850000.0,
    "cogs": 215000.0,
    "profit": 635000.0,
    "margin": 74.71,
    "transactions": 25
  }
}
```

> 💡 `transactions` = jumlah baris penjualan, bukan jumlah item terjual.

---

#### GET `/api/reports/pnl`

Laporan Profit & Loss bulanan.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `month` | integer | bulan ini | Bulan (1-12) |
| `year` | integer | tahun ini | Tahun (2000-2100) |

**Response 200:**

```json
{
  "success": true,
  "message": "Laporan P&L.",
  "data": {
    "month": 6,
    "year": 2026,
    "revenue": 25000000.0,
    "cogs": 8750000.0,
    "gross_profit": 16250000.0,
    "gross_margin": 65.0,
    "expenses": [
      {
        "category": "operasional",
        "amount": 3500000.0
      },
      {
        "category": "bahan_baku",
        "amount": 500000.0
      },
      {
        "category": "overhead",
        "amount": 1500000.0
      }
    ],
    "total_expenses": 5500000.0,
    "expenses_by_category": {
      "bahan_baku": 500000.0,
      "operasional": 3500000.0,
      "overhead": 1500000.0
    },
    "net_profit": 10750000.0,
    "net_margin": 43.0,
    "period_label": "Juni 2026"
  }
}
```

> 💡 `period_label` dalam Bahasa Indonesia (locale `id`). Hanya kategori P&L yang masuk (`bahan_baku`, `operasional`, `overhead`).

---

#### GET `/api/reports/stock-alerts`

Daftar bahan yang stoknya menipis atau kritis.

**Response 200:**

```json
{
  "success": true,
  "message": "Alert stok.",
  "data": {
    "alerts": [
      {
        "id": 3,
        "ingredient": "matcha powder",
        "current_stock": 15.0,
        "minimum_stock": 100.0,
        "unit": "gram",
        "status": "kritis"
      },
      {
        "id": 2,
        "ingredient": "susu uht",
        "current_stock": 2.0,
        "minimum_stock": 5.0,
        "unit": "liter",
        "status": "menipis"
      }
    ],
    "alert_count": 2,
    "safe_count": 18
  }
}
```

---

#### GET `/api/reports/margin-alerts`

Produk yang margin-nya turun dibanding ~1 bulan lalu.

**Response 200:**

```json
{
  "success": true,
  "message": "Alert margin.",
  "data": {
    "alerts": [
      {
        "product_id": 1,
        "product": "Matcha Latte",
        "selling_price": 35000.0,
        "previous_margin": 78.57,
        "current_margin": 72.14,
        "margin_drop": 6.43,
        "previous_cogs": 7500.0,
        "current_cogs": 9750.0
      }
    ],
    "alert_count": 1
  }
}
```

> 💡 Alert terpicu jika margin turun ≥ 2 poin persen.

---

#### GET `/api/reports/top-products`

Produk paling laku dalam periode tertentu.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `month` | integer | bulan ini | Bulan |
| `year` | integer | tahun ini | Tahun |
| `limit` | integer | `5` | Jumlah item (1-10) |

**Response 200:**

```json
{
  "success": true,
  "message": "Produk paling laku.",
  "data": {
    "period_label": "Juni 2026",
    "items": [
      {
        "product_id": 1,
        "product": "Matcha Latte",
        "quantity": 450,
        "revenue": 15750000.0,
        "profit": 12375000.0,
        "transactions": 120
      },
      {
        "product_id": 3,
        "product": "Croissant",
        "quantity": 380,
        "revenue": 6840000.0,
        "profit": 3420000.0,
        "transactions": 95
      }
    ]
  }
}
```

---

#### GET `/api/reports/bottom-products`

Produk paling sepi (kebalikan dari top). Parameter sama.

**Response:** Format sama seperti `top-products`, tapi diurutkan dari yang paling sedikit terjual.

---

#### GET `/api/reports/aging`

Laporan aging piutang (receivable) secara keseluruhan.

**Response 200:**

```json
{
  "success": true,
  "message": "Laporan aging.",
  "data": {
    "summary": {
      "total_outstanding": 15000000.0,
      "total_partners": 3
    },
    "by_partner": [
      {
        "partner_id": 2,
        "partner": "Toko Budi Jaya",
        "total": 8000000.0,
        "current": 5000000.0,
        "1-2_months": 2000000.0,
        "2-3_months": 1000000.0,
        "3_plus": 0.0
      }
    ],
    "by_aging": {
      "current": 8000000.0,
      "1-2_months": 4000000.0,
      "2-3_months": 2000000.0,
      "3_plus": 1000000.0
    }
  }
}
```

---

### 4.8 Partner

#### GET `/api/partners`

Daftar partner (customer/supplier).

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `type` | string | — | Filter: `customer` atau `supplier` |

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Toko Budi Jaya",
      "type": "customer",
      "contact": "Budi",
      "phone": "08123456789",
      "email": "budi@tokobudi.com",
      "address": "Jl. Merdeka No. 10"
    }
  ]
}
```

---

#### POST `/api/partners`

Buat partner baru.

**Request:**

```json
{
  "name": "Toko Budi Jaya",
  "type": "customer",
  "contact": "Budi",
  "phone": "08123456789",
  "email": "budi@tokobudi.com",
  "address": "Jl. Merdeka No. 10"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | ✅ | Nama partner (unik per tenant) |
| `type` | string | ✅ | `customer` atau `supplier` |
| `contact` | string | ❌ | Nama kontak person |
| `phone` | string | ❌ | Nomor telepon (maks 50) |
| `email` | string | ❌ | Email (valid format) |
| `address` | string | ❌ | Alamat (maks 1000) |

**Response 201:** Object partner yang dibuat.

---

#### GET `/api/partners/{partner}`

Detail partner + info piutang.

**Response 200:**

```json
{
  "id": 1,
  "name": "Toko Budi Jaya",
  "type": "customer",
  "contact": "Budi",
  "phone": "08123456789",
  "email": "budi@tokobudi.com",
  "address": "Jl. Merdeka No. 10",
  "outstanding_invoices": 2,
  "total_outstanding": 8000000.0,
  "aging": {
    "current": 5000000.0,
    "1-2_months": 2000000.0,
    "2-3_months": 1000000.0,
    "3_plus": 0.0
  }
}
```

---

#### PUT `/api/partners/{partner}`

Update partner. Semua field bersifat `sometimes` (opsional, kirim yang ingin diubah saja).

**Request:** Field sama seperti POST, tanpa `name` yang wajib (kecuali diisi).

**Response 200:** Object partner yang diperbarui.

---

#### DELETE `/api/partners/{partner}`

Hapus partner. ⚠️ Gagal jika partner masih punya invoice outstanding.

**Response 200:**

```json
{
  "message": "Partner dihapus."
}
```

**Response 422:**

```json
{
  "message": "Partner masih punya invoice outstanding."
}
```

---

#### GET `/api/partners/{partner}/aging`

Laporan aging spesifik untuk satu partner.

**Response 200:**

```json
{
  "partner_id": 1,
  "partner": "Toko Budi Jaya",
  "total_outstanding": 8000000.0,
  "outstanding_invoices": 2,
  "aging": {
    "current": 5000000.0,
    "1-2_months": 2000000.0,
    "2-3_months": 1000000.0,
    "3_plus": 0.0
  }
}
```

---

### 4.9 Invoice

#### GET `/api/invoices/outstanding`

Daftar invoice yang belum lunas.

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-20260601-001",
      "partner_id": 1,
      "partner": "Toko Budi Jaya",
      "amount": 5000000.0,
      "paid_amount": 2000000.0,
      "remaining": 3000000.0,
      "due_date": "2026-07-01",
      "status": "partial",
      "note": null,
      "paid_at": null
    }
  ]
}
```

---

#### GET `/api/invoices`

Daftar semua invoice. Bisa difilter.

**Query Parameters:**

| Field | Type | Keterangan |
|---|---|---|
| `status` | string | Filter: `outstanding`, `partial`, `paid` |

**Response:** Format sama seperti outstanding.

---

#### POST `/api/invoices`

Buat invoice baru (hanya untuk partner tipe `customer`).

**Request:**

```json
{
  "partner_id": 1,
  "amount": 5000000,
  "due_date": "2026-07-01",
  "note": "Invoice bulanan Juni"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `partner_id` | integer | ✅ | ID partner (harus tipe `customer`) |
| `amount` | numeric | ✅ | Nominal tagihan. Harus > 0 |
| `due_date` | date | ✅ | Tanggal jatuh tempo |
| `note` | string | ❌ | Catatan (maks 1000) |

**Response 201:**

```json
{
  "message": "Invoice dibuat.",
  "invoice": {
    "id": 10,
    "invoice_number": "INV-20260620-001",
    "partner_id": 1,
    "partner": "Toko Budi Jaya",
    "amount": 5000000.0,
    "paid_amount": 0.0,
    "remaining": 5000000.0,
    "due_date": "2026-07-01",
    "status": "outstanding",
    "note": "Invoice bulanan Juni",
    "paid_at": null
  }
}
```

---

#### GET `/api/invoices/{invoice}`

Detail satu invoice.

**Response 200:** Object invoice (format sama seperti di atas).

---

#### PUT `/api/invoices/{invoice}`

Update invoice (amount, due_date, note). ⚠️ Tidak bisa update invoice yang sudah lunas.

**Request:** Field bersifat `sometimes`.

| Field | Type | Keterangan |
|---|---|---|
| `amount` | numeric | Nominal baru. Tidak boleh kurang dari `paid_amount` yang sudah ada |
| `due_date` | date | Tanggal jatuh tempo baru |
| `note` | string | Catatan baru |

**Response 200:**

```json
{
  "message": "Invoice diperbarui.",
  "invoice": { ... }
}
```

---

#### POST `/api/invoices/{invoice}/pay`

Catat pembayaran invoice (bisa cicilan/partial).

**Request:**

```json
{
  "amount": 2000000,
  "paid_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `amount` | numeric | ✅ | Nominal yang dibayarkan. Harus > 0 |
| `paid_at` | date | ❌ | Tanggal pembayaran. Default: `now()` |

**Response 200:**

```json
{
  "message": "Pembayaran tercatat.",
  "invoice": {
    "id": 10,
    "invoice_number": "INV-20260620-001",
    "amount": 5000000.0,
    "paid_amount": 2000000.0,
    "remaining": 3000000.0,
    "status": "partial"
  }
}
```

**Status Otomatis:**

| Kondisi | Status |
|---|---|
| `paid_amount == 0` | `outstanding` |
| `0 < paid_amount < amount` | `partial` |
| `paid_amount >= amount` | `paid` |

---

### 4.10 Production Runs

Production run untuk produk tipe **batch** (bukan unit). Proses: bahan baku dikurangi dari stok → produk jadi masuk stok saat yield dicatat.

#### GET `/api/production-runs`

Daftar riwayat produksi.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `product_id` | integer | — | Filter per produk |
| `date` | string | — | Filter tanggal |
| `from` | string | — | Tanggal awal (combine dengan `to`) |
| `to` | string | — | Tanggal akhir |
| `limit` | integer | `10` | Jumlah baris (maks 50) |

**Response 200:**

```json
{
  "success": true,
  "message": "Riwayat produksi.",
  "data": [
    {
      "id": 5,
      "product": "Croissant Batch",
      "batch_count": 10,
      "yield_actual": 180,
      "waste_count": 20,
      "total_cost": 2250000.0,
      "cost_per_unit": 12500.0,
      "yield_per_batch": 18.0,
      "waste_percentage": 10.0,
      "notes": "Batch pagi",
      "produced_at": "2026-06-20T06:00:00+07:00"
    }
  ]
}
```

---

#### POST `/api/production-runs`

Buat production run baru. Otomatis mengurangi bahan baku sesuai resep × jumlah batch.

**Request:**

```json
{
  "product_id": 5,
  "batch_count": 10,
  "notes": "Batch pagi",
  "produced_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `product_id` | integer | ✅ | ID produk (harus tipe `batch`) |
| `batch_count` | integer | ✅ | Jumlah batch. Minimal 1 |
| `notes` | string | ❌ | Catatan (maks 255) |
| `produced_at` | date | ❌ | Waktu produksi. Default: `now()` |

> ⚠️ Produk harus `recipe_type = batch` dan punya resep. Stok bahan baku harus mencukupi.

**Response 201:**

```json
{
  "success": true,
  "message": "Produksi tercatat.",
  "data": {
    "id": 5,
    "product": "Croissant Batch",
    "batch_count": 10,
    "yield_actual": 0,
    "waste_count": 0,
    "total_cost": 2250000.0,
    "cost_per_unit": 0.0,
    "items": [
      {
        "ingredient": "tepung terigu",
        "quantity_used": 5000.0,
        "unit_cost_snapshot": 12.0,
        "total_cost": 60000.0
      },
      {
        "ingredient": "mentega",
        "quantity_used": 1500.0,
        "unit_cost_snapshot": 50.0,
        "total_cost": 75000.0
      }
    ],
    "produced_at": "2026-06-20T06:00:00+07:00"
  }
}
```

> 💡 `yield_actual` dan `waste_count` masih 0 saat create. Yield dicatat kemudian.

---

#### GET `/api/production-runs/{productionRun}`

Detail satu production run, termasuk item bahan yang dipakai.

**Response 200:**

```json
{
  "success": true,
  "message": "Detail produksi.",
  "data": {
    "id": 5,
    "product": "Croissant Batch",
    "batch_count": 10,
    "yield_actual": 180,
    "waste_count": 20,
    "total_cost": 2250000.0,
    "cost_per_unit": 12500.0,
    "yield_per_batch": 18.0,
    "waste_percentage": 10.0,
    "items": [
      {
        "ingredient": "tepung terigu",
        "quantity_used": 5000.0,
        "unit_cost_snapshot": 12.0,
        "total_cost": 60000.0
      }
    ],
    "notes": "Batch pagi",
    "produced_at": "2026-06-20T06:00:00+07:00"
  }
}
```

---

#### DELETE `/api/production-runs/{productionRun}`

Batalkan production run. Mengembalikan stok bahan baku dan menghapus produk jadi dari stok.

**Response 200:**

```json
{
  "success": true,
  "message": "Produksi dibatalkan. Stok dikembalikan."
}
```

---

## 5. Endpoint yang Perlu Dibangun (Missing)

Berikut adalah endpoint yang **belum ada** di backend tetapi dibutuhkan untuk fitur-fitur baru bot.

---

### 5.1 Ingredients — CRUD + Stock Adjustment

#### GET `/api/ingredients`

Daftar semua bahan baku dengan detail.

**Suggested Controller:** `IngredientController`

```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "name": "kopi arabika",
      "item_type": "raw_material",
      "unit_type": "gramasi",
      "base_unit": "gram",
      "unit_price": 0.90,
      "weighted_avg_price": 0.87,
      "current_stock": 3500.0,
      "minimum_stock": 1000.0,
      "stock_status": "aman",
      "supplier_id": 1
    }
  ]
}
```

---

#### GET `/api/ingredients/{ingredient}`

Detail satu bahan + riwayat harga.

```json
// Response 200
{
  "id": 1,
  "name": "kopi arabika",
  "item_type": "raw_material",
  "base_unit": "gram",
  "unit_price": 0.90,
  "weighted_avg_price": 0.87,
  "current_stock": 3500.0,
  "minimum_stock": 1000.0,
  "stock_status": "aman",
  "price_history": [
    { "unit_price": 0.85, "recorded_at": "2026-06-01" },
    { "unit_price": 0.90, "recorded_at": "2026-06-15" }
  ]
}
```

---

#### POST `/api/ingredients`

Buat bahan baru.

```json
// Request
{
  "name": "kopi robusta",
  "item_type": "raw_material",
  "unit_type": "gramasi",
  "base_unit": "gram",
  "unit_price": 55.0,
  "current_stock": 0,
  "minimum_stock": 500,
  "supplier_id": null
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | ✅ | Nama bahan (unik) |
| `item_type` | string | ✅ | `raw_material`, `prep`, `finished_goods` |
| `unit_type` | string | ✅ | `gramasi` (g, ml, pcs) atau `packaged` (sachet, pack) |
| `base_unit` | string | ✅ | Satuan dasar (gram, ml, liter, pcs, sachet) |
| `unit_price` | numeric | ✅ | Harga beli per unit |
| `minimum_stock` | numeric | ✅ | Stok minimum |
| `supplier_id` | integer | ❌ | ID supplier |

---

#### PUT `/api/ingredients/{ingredient}`

Update bahan.

---

#### DELETE `/api/ingredients/{ingredient}`

Hapus bahan. ⚠️ Gagal jika masih dipakai di resep atau transaksi.

---

#### POST `/api/ingredients/{ingredient}/adjust`

Koreksi manual stok bahan (set ke nilai absolut).

```json
// Request
{
  "new_stock": 3200,
  "note": "Koreksi setelah stock opname"
}
```

```json
// Response 200
{
  "message": "Stok dikoreksi.",
  "data": {
    "id": 1,
    "ingredient": "kopi arabika",
    "previous_stock": 3500.0,
    "new_stock": 3200.0,
    "delta": -300.0,
    "stock_status": "aman"
  }
}
```

> 💡 Menggunakan `InventoryService::adjustStock()`. Catatan: `AdjustStockRequest` sudah ada di `app/Http/Requests/`.

---

### 5.2 Prep Stocks — GET

#### GET `/api/stocks/prep`

Daftar stok bahan setengah jadi (prep items).

```json
// Response 200
{
  "data": [
    {
      "id": 20,
      "name": "Croissant Batch ( Prep )",
      "item_type": "prep",
      "base_unit": "pcs",
      "current_stock": 180.0,
      "minimum_stock": 50.0,
      "stock_status": "aman"
    }
  ]
}
```

---

### 5.3 Finished Goods — GET + Yield Recording

#### GET `/api/stocks/finished-goods`

Daftar stok produk jadi.

```json
// Response 200
{
  "data": [
    {
      "id": 21,
      "name": "Croissant Batch ( Produk Jadi )",
      "item_type": "finished_goods",
      "base_unit": "pcs",
      "current_stock": 180.0,
      "minimum_stock": 0,
      "stock_status": "aman"
    }
  ]
}
```

---

#### POST `/api/production-runs/{productionRun}/yield`

Catat yield aktual setelah produksi selesai. Stok produk jadi otomatis bertambah.

```json
// Request
{
  "yield_actual": 180,
  "waste_count": 20
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `yield_actual` | integer | ✅ | Jumlah produk jadi yang dihasilkan. Harus > 0 |
| `waste_count` | integer | ❌ | Jumlah yang rusak/layu. Default: 0 |

```json
// Response 200
{
  "success": true,
  "message": "Yield tercatat.",
  "data": {
    "id": 5,
    "yield_actual": 180,
    "waste_count": 20,
    "cost_per_unit": 12500.0,
    "waste_percentage": 10.0,
    "finished_goods_stock": 180.0
  }
}
```

> 💡 Menggunakan `ProductionRunService::updateYield()`. Pertama kali: yield ditambahkan ke stok. Edit: selisih disesuaikan.

---

### 5.4 Production Run Items — Update (Edit Bahan)

#### PUT `/api/production-runs/{productionRun}/items`

Update daftar bahan yang dipakai dalam produksi. Stok disesuaikan otomatis.

```json
// Request
{
  "items": [
    {
      "ingredient_id": 1,
      "quantity_used": 5500
    },
    {
      "ingredient_id": 2,
      "quantity_used": 1600
    }
  ]
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `items` | array | ✅ | Array item bahan |
| `items.*.ingredient_id` | integer | ✅ | ID bahan |
| `items.*.quantity_used` | numeric | ✅ | Jumlah yang dipakai |

```json
// Response 200
{
  "success": true,
  "message": "Item produksi diperbarui.",
  "data": {
    "id": 5,
    "total_cost": 2350000.0,
    "items": [
      {
        "ingredient_id": 1,
        "ingredient": "tepung terigu",
        "quantity_used": 5500.0,
        "unit_cost_snapshot": 12.0,
        "total_cost": 66000.0
      }
    ]
  }
}
```

> 💡 Menggunakan `ProductionRunService::updateItems()`. Stok bahan otomatis disesuaikan (lebih pakai → kurangi stok, kurang pakai → kembalikan stok).

---

### 5.5 Cash Entries — POST/DELETE

#### POST `/api/cash-entries`

Catat modal masuk (inject modal ke usaha).

```json
// Request
{
  "type": "modal_tambahan",
  "amount": 5000000,
  "description": "Inject modal dari owner",
  "occurred_at": "2026-06-20"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `type` | string | ✅ | `modal_awal`, `modal_tambahan`, `lainnya` |
| `amount` | numeric | ✅ | Nominal. Harus > 0 |
| `description` | string | ❌ | Deskripsi (maks 255) |
| `occurred_at` | date | ❌ | Tanggal. Default: `now()` |

**Tipe Cash Entry:**

| Kode | Label |
|---|---|
| `modal_awal` | Modal Awal |
| `modal_tambahan` | Modal Tambahan |
| `lainnya` | Lainnya |

```json
// Response 201
{
  "success": true,
  "message": "Modal tercatat.",
  "data": {
    "id": 10,
    "type": "modal_tambahan",
    "amount": 5000000.0,
    "description": "Inject modal dari owner",
    "occurred_at": "2026-06-20"
  }
}
```

> 💡 `CashEntry` model sudah ada. Yang perlu dibuat: controller + route + form request.

---

#### DELETE `/api/cash-entries/{cashEntry}`

Hapus catatan modal. ⚠️ Hanya bisa hapus jika tidak ada transaksi terkait di bulan yang sama.

---

### 5.6 Cashflow Report — GET

#### GET `/api/reports/cashflow`

Laporan arus kas bulanan.

**Query Parameters:**

| Field | Type | Default |
|---|---|---|
| `month` | integer | bulan ini |
| `year` | integer | tahun ini |

```json
// Response 200
{
  "success": true,
  "message": "Laporan cashflow.",
  "data": {
    "month": 6,
    "year": 2026,
    "saldo_awal": 15000000.0,
    "kas_masuk": {
      "penjualan": 25000000.0,
      "modal": 5000000.0
    },
    "total_kas_masuk": 30000000.0,
    "kas_keluar": {
      "pembelian": 8750000.0,
      "biaya_operasional": 5500000.0,
      "di_luar_usaha": 0.0
    },
    "total_kas_keluar": 14250000.0,
    "saldo_akhir": 30750000.0
  }
}
```

> 💡 `CashflowService` sudah ada. Yang perlu dibuat: controller method + route.

---

### 5.7 Product Create/Update + Recipe Management

#### POST `/api/products`

Buat produk baru beserta resepnya.

```json
// Request
{
  "name": "Cappuccino",
  "unit": "pcs",
  "selling_price": 30000,
  "recipe_type": "unit",
  "estimated_yield_per_batch": null,
  "is_prep": false,
  "recipe_items": [
    {
      "ingredient_id": 1,
      "quantity": 18
    },
    {
      "ingredient_id": 2,
      "quantity": 150
    }
  ]
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | ✅ | Nama produk |
| `unit` | string | ✅ | Satuan jual (pcs, gelas, dll) |
| `selling_price` | numeric | ✅ | Harga jual |
| `recipe_type` | string | ✅ | `unit` (per porsi) atau `batch` (per batch produksi) |
| `estimated_yield_per_batch` | integer | ❌ | Estimasi yield per batch (untuk tipe batch) |
| `is_prep` | boolean | ❌ | `true` jika produk ini adalah bahan setengah jadi |
| `recipe_items` | array | ❌ | Resep: array `{ingredient_id, quantity}` |

---

#### PUT `/api/products/{product}`

Update produk. Resep bisa di-replace seluruhnya via `recipe_items`.

---

### 5.8 Expense Categories — GET

#### GET `/api/expense-categories`

Daftar kategori expense yang tersedia.

```json
// Response 200
{
  "data": [
    { "key": "bahan_baku", "label": "Bahan Baku", "includes_pnl": true },
    { "key": "operasional", "label": "Operasional", "includes_pnl": true },
    { "key": "overhead", "label": "Overhead", "includes_pnl": true },
    { "key": "non_operasional", "label": "Di Luar Usaha", "includes_pnl": false }
  ]
}
```

> 💡 Simpel, hardcode dari `Expense::CATEGORIES`. Berguna agar bot bisa menampilkan pilihan kategori ke user.

---

### 5.9 Sales Detail — Per Product Breakdown

#### GET `/api/reports/sales-detail`

Rincian penjualan per produk dalam periode tertentu.

**Query Parameters:**

| Field | Type | Default | Keterangan |
|---|---|---|---|
| `date` | string | — | Filter tanggal spesifik |
| `month` | integer | bulan ini | Bulan |
| `year` | integer | tahun ini | Tahun |

```json
// Response 200
{
  "success": true,
  "message": "Rincian penjualan.",
  "data": {
    "period_label": "20 Juni 2026",
    "summary": {
      "total_revenue": 850000.0,
      "total_cogs": 215000.0,
      "total_profit": 635000.0,
      "total_transactions": 25,
      "total_items_sold": 45
    },
    "items": [
      {
        "product_id": 1,
        "product": "Matcha Latte",
        "quantity": 15,
        "revenue": 525000.0,
        "cogs": 112500.0,
        "profit": 412500.0,
        "margin": 78.57,
        "transactions": 10
      },
      {
        "product_id": 2,
        "product": "Croissant",
        "quantity": 10,
        "revenue": 180000.0,
        "cogs": 90000.0,
        "profit": 90000.0,
        "margin": 50.0,
        "transactions": 8
      }
    ]
  }
}
```

---

## 6. Data Models — Referensi Cepat

### Ingredient (Bahan Baku / Prep / Finished Goods)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `name` | string | Nama bahan (unik per tenant) |
| `item_type` | string | `raw_material`, `prep`, `finished_goods` |
| `unit_type` | string | `gramasi` atau `packaged` |
| `base_unit` | string | Satuan dasar: gram, ml, liter, pcs, sachet |
| `unit_price` | decimal(4) | Harga beli terakhir per base unit |
| `weighted_avg_price` | decimal(4) | Harga rata-rata tertimbang |
| `current_stock` | decimal(4) | Stok saat ini (computed dari ledger) |
| `minimum_stock` | decimal(4) | Stok minimum |
| `stock_status` | string (appended) | `aman`, `menipis`, `kritis` |

### Product (Produk Jadi)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `name` | string | Nama produk |
| `unit` | string | Satuan jual (pcs, gelas) |
| `selling_price` | decimal(2) | Harga jual |
| `recipe_type` | string | `unit` (per porsi) atau `batch` (per batch) |
| `estimated_yield_per_batch` | integer | Estimasi jumlah jadi per batch |
| `is_active` | boolean | Aktif ditampilkan |
| `is_prep` | boolean | Apakah ini bahan setengah jadi |

### RecipeItem (Resep)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `product_id` | integer | FK → Product |
| `ingredient_id` | integer | FK → Ingredient |
| `quantity` | decimal(4) | Kuantitas per 1 porsi/batch |

### Transaction (Pembelian)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `ingredient_id` | integer | FK → Ingredient |
| `quantity` | decimal(4) | Kuantitas dibeli |
| `unit_price` | decimal(4) | Harga per unit saat beli |
| `total` | decimal(2) | quantity × unit_price |
| `source` | string | `bot`, `web` |
| `note` | string | Catatan |
| `occurred_at` | datetime | Waktu transaksi |

### Sale (Penjualan)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `product_id` | integer | FK → Product |
| `quantity` | integer | Jumlah terjual |
| `unit_price` | decimal(2) | Harga jual per unit |
| `revenue` | decimal(2) | unit_price × quantity |
| `cogs` | decimal(2) | Total COGS |
| `profit` | decimal(2) | revenue − cogs |
| `margin` | decimal(2) | Profit margin (%) |
| `source` | string | `bot`, `web` |
| `occurred_at` | datetime | Waktu penjualan |

### Expense (Biaya)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `category` | string | `bahan_baku`, `operasional`, `overhead`, `non_operasional` |
| `description` | string | Deskripsi |
| `amount` | decimal(2) | Nominal |
| `period_month` | integer | Bulan (1-12) |
| `period_year` | integer | Tahun |

### ProductionRun (Proses Produksi)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `product_id` | integer | FK → Product (harus batch type) |
| `batch_count` | integer | Jumlah batch |
| `yield_actual` | integer | Jumlah produk jadi aktual |
| `waste_count` | integer | Jumlah rusak |
| `total_cost` | decimal(4) | Total biaya produksi |
| `notes` | string | Catatan |
| `produced_at` | datetime | Waktu produksi |

### ProductionRunItem (Bahan Produksi)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `production_run_id` | integer | FK → ProductionRun |
| `ingredient_id` | integer | FK → Ingredient |
| `quantity_used` | decimal(4) | Jumlah yang dipakai |
| `unit_cost_snapshot` | decimal(4) | Harga satuan saat produksi |

### CashEntry (Modal)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `type` | string | `modal_awal`, `modal_tambahan`, `lainnya` |
| `amount` | decimal(2) | Nominal |
| `description` | string | Deskripsi |
| `occurred_at` | date | Tanggal |

### Partner

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `name` | string | Nama (unik per tenant) |
| `type` | string | `customer`, `supplier` |
| `contact` | string | Nama kontak |
| `phone` | string | Telepon |
| `email` | string | Email |
| `address` | string | Alamat |

### Invoice (Tagihan)

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `partner_id` | integer | FK → Partner |
| `invoice_number` | string | Nomor invoice (auto-generated) |
| `amount` | decimal(2) | Total tagihan |
| `paid_amount` | decimal(2) | Sudah dibayar |
| `due_date` | date | Jatuh tempo |
| `status` | string | `outstanding`, `partial`, `paid` |
| `note` | string | Catatan |
| `paid_at` | datetime | Waktu pelunasan |

### Tenant

| Field | Type | Keterangan |
|---|---|---|
| `id` | integer | PK |
| `name` | string | Nama bisnis |
| `plan` | string | `free`, `pro` |
| `bot_token` | string (hashed) | Token bot yang di-hash |

---

## 7. Error Responses

### Standard Error Format

```json
{
  "success": false,
  "message": "Deskripsi error dalam Bahasa Indonesia",
  "error_code": "ERROR_CODE"
}
```

### Error Codes yang Sudah Ada

| HTTP | error_code | Keterangan |
|---|---|---|
| 401 | `UNAUTHORIZED` | Token tidak valid atau tidak ada |
| 422 | `INGREDIENT_NOT_FOUND` | Bahan tidak ditemukan (ada `available_items`) |
| 422 | `PRODUCT_NOT_FOUND` | Produk tidak ditemukan (ada `available_items`) |
| 422 | `BATCH_VALIDATION_FAILED` | Beberapa item batch gagal validasi (ada `errors`) |
| 429 | `AI_QUOTA_EXCEEDED` | Kuota AI harian habis |
| 422 | `VALIDATION_ERROR` | Validasi umum (bulan/tahun tidak valid, dll) |
| 422 | `NOT_BATCH_PRODUCT` | Produk bukan tipe batch |
| 422 | `REVERSAL_ERROR` | Gagal membatalkan produksi |

### Laravel Validation Error (422)

Jika gagal validasi Laravel (Form Request), response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "ingredient": ["ingredient_id atau ingredient wajib diisi."],
    "quantity": ["The quantity field is required."]
  }
}
```

### Stok Tidak Cukup

```json
{
  "success": false,
  "message": "Stok \"matcha powder\" tidak cukup. Tersedia: 15 gram, diperlukan: 100 gram.",
  "error_code": "VALIDATION_ERROR"
}
```

---

## Ringkasan Endpoint Missing (untuk Development)

| # | Endpoint | Method | Service | Status |
|---|---|---|---|---|
| 1 | `/api/ingredients` | GET | — | 🔴 Belum ada |
| 2 | `/api/ingredients/{id}` | GET | — | 🔴 Belum ada |
| 3 | `/api/ingredients` | POST | — | 🔴 Belum ada |
| 4 | `/api/ingredients/{id}` | PUT | — | 🔴 Belum ada |
| 5 | `/api/ingredients/{id}` | DELETE | — | 🔴 Belum ada |
| 6 | `/api/ingredients/{id}/adjust` | POST | InventoryService | 🔴 Belum ada |
| 7 | `/api/stocks/prep` | GET | — | 🔴 Belum ada |
| 8 | `/api/stocks/finished-goods` | GET | — | 🔴 Belum ada |
| 9 | `/api/production-runs/{id}/yield` | POST | ProductionRunService | 🔴 Belum ada |
| 10 | `/api/production-runs/{id}/items` | PUT | ProductionRunService | 🔴 Belum ada |
| 11 | `/api/cash-entries` | POST | — | 🔴 Belum ada |
| 12 | `/api/cash-entries/{id}` | DELETE | — | 🔴 Belum ada |
| 13 | `/api/reports/cashflow` | GET | CashflowService | 🔴 Belum ada |
| 14 | `/api/products` | POST | — | 🔴 Belum ada |
| 15 | `/api/products/{id}` | PUT | — | 🔴 Belum ada |
| 16 | `/api/expense-categories` | GET | — | 🔴 Belum ada |
| 17 | `/api/reports/sales-detail` | GET | — | 🔴 Belum ada |

> Semua service sudah ada di `app/Services/`. Yang perlu dibangun: **controller + route + form request**.
