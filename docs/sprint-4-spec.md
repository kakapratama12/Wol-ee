# Sprint 4 Spec: Bot Integration

> **Status:** Ready for implementation
> **Depends on:** Sprint 1.1 (multi-tenant) + Sprint 2 (partner/invoice)

---

## 1. Architecture

```
User (Telegram)
    |
Bot (Python) -- parse NL, extract data
    |
HTTP POST -> Laravel API
    |
Laravel: validate, save ke DB
    |
Laravel: return JSON response
    |
Bot: reply ke user (success/error)
    |
Dashboard: auto-update (real-time dari DB yang sama)
```

---

## 2. Authentication

---

## 1.5 User Registration Flow

### Flow:
```
1. Owner setup (sekali aja):
   - Login ke dashboard
   - Buka Settings > Bot Integration
   - Copy API token: 1:abc123def456

2. Staff registration:
   - Buka bot Telegram: @wol_ee_bot
   - Ketik: /start
   - Bot tanya: "Masukkan token dari dashboard"
   - Staff paste: 1:abc123def456
   - Bot validate token via API
   - Bot simpan: {telegram_user_id: 123456, token: "1:abc123"}
   - Bot reply: "Berhasil terdaftar! Ketik 'bantuan' untuk lihat perintah."

3. Selesai:
   - Staff ketik: "Beli tepung 2kg"
   - Bot tau: ini dari tenant 1 (dari token)
   - Data masuk ke Kafe Contoh
```

### Bot Storage:
```
bot_users table:
- telegram_user_id (primary key)
- tenant_id (foreign key)
- token (hashed)
- registered_at
- last_active_at
```

### Registration Validation:
```
Bot kirim token ke Laravel: POST /api/bot/validate-token

Request:
{
  "token": "1:abc123def456"
}

Response (valid):
{
  "success": true,
  "tenant": {
    "id": 1,
    "name": "Kafe Contoh",
    "plan": "pro"
  }
}

Response (invalid):
{
  "success": false,
  "message": "Token tidak valid"
}
```

### Multi-User同一Tenant:
```
Kafe Contoh punya 3 staff:
├── Staff A (telegram: 111) → token: 1:abc123
├── Staff B (telegram: 222) → token: 1:abc123
├── Staff C (telegram: 333) → token: 1:abc123
└── Semua input masuk ke tenant 1 (Kafe Contoh)
```



### 2.1 Token Structure
```
Token format: {tenant_id}:{secret_key}
Contoh: 1:abc123def456ghi789

Storage:
- tenants table -> column: bot_token (hashed)
- Bot config: WOL_EE_API_TOKEN=1:abc123def456ghi789
```

### 2.2 Token Generation
```
Artisan command: php artisan wol-ee:generate-bot-token --tenant=1
- Generate token untuk tenant tertentu
- Store hashed di tenants.bot_token
- Return plain text (simpan di bot config)
```

### 2.3 Auth Middleware
```
app/Http/Middleware/BotTokenAuth.php
1. Parse token dari Authorization header
2. Extract tenant_id
3. Find tenant by id
4. Verify secret against hashed token
5. Set auth()->user() to tenant owner
6. Set tenant scope
```

---

## 3. API Endpoints (untuk Bot)

Base URL: https://your-domain.com/api
Auth: Authorization: Bearer {tenant_id}:{secret}

### 3.1 Input Pembelian

POST /api/transactions

Request:
{
  "ingredient": "tepung",
  "quantity": 2,
  "unit_price": 18000,
  "note": "",
  "occurred_at": "2026-06-18"
}

Response (success):
{
  "success": true,
  "message": "Pembelian tercatat.",
  "data": {
    "id": 1,
    "ingredient": "tepung",
    "quantity": 2,
    "unit_price": 18000,
    "total": 36000,
    "new_stock": 5.5,
    "stock_status": "aman"
  }
}

Response (not found):
{
  "success": false,
  "message": "Bahan 'tepung van' tidak ditemukan.",
  "suggestions": ["Tepung terigu", "Tepung beras"],
  "error_code": "INGREDIENT_NOT_FOUND"
}

### 3.2 Input Penjualan

POST /api/sales

Request:
{
  "product": "Matcha Latte",
  "quantity": 10,
  "unit_price": 25000,
  "note": "",
  "occurred_at": "2026-06-18"
}

Response (success):
{
  "success": true,
  "message": "Penjualan tercatat.",
  "data": {
    "id": 1,
    "product": "Matcha Latte",
    "quantity": 10,
    "revenue": 250000,
    "cogs": 120000,
    "profit": 130000,
    "margin": 52.0,
    "alerts": [
      {"ingredient": "Matcha powder", "current": 1.2, "minimum": 2, "status": "menipis"}
    ]
  }
}

### 3.3 Cek Stok

GET /api/stock
GET /api/stock?ingredient=tepung

Response:
{
  "success": true,
  "data": [
    {
      "ingredient": "tepung",
      "current_stock": 5.5,
      "minimum_stock": 2,
      "unit": "kg",
      "status": "aman"
    }
  ]
}

### 3.4 Cek Aging

GET /api/reports/aging

Response:
{
  "success": true,
  "data": {
    "summary": {
      "total_outstanding": 18000000,
      "total_partners": 5
    },
    "by_partner": [
      {
        "partner": "CV Maju Jaya",
        "total": 15000000,
        "current": 8000000,
        "1-2_months": 5000000,
        "2-3_months": 1500000,
        "3_plus": 500000
      }
    ]
  }
}

---

## 4. Bot Changes (Python)

### 4.1 Config
```
# config.py
WOL_EE_API_URL = "https://your-domain.com/api"
WOL_EE_API_TOKEN = "1:abc123def456ghi789"
```

### 4.2 API Client (wol_ee_client.py)
```
class WolEeClient:
    def __init__(self, base_url, token):
        self.base_url = base_url
        self.headers = {"Authorization": f"Bearer {token}"}

    def post_transaction(self, data):
        POST /transactions -> return json

    def post_sale(self, data):
        POST /sales -> return json

    def get_stock(self, ingredient=None):
        GET /stock -> return json

    def get_aging(self):
        GET /reports/aging -> return json
```

### 4.3 Updated Bot Handler
```
handle_pembelian(user_id, text):
1. Parse NL -> {item, qty, unit, price}
2. client.post_transaction(data)
3. If success -> reply "Pembelian tercatat. Stok: X kg (status)"
4. If not found -> reply "Bahan X tidak ditemukan. Maksudnya: Y?"
5. If timeout -> save to local queue, reply "Tersimpan offline"
```

---

## 5. Error Handling

### Bot Side:
- Timeout -> save ke local queue, retry later
- API error -> log, return generic message

### Laravel Side:
- Konsisten return format:
  {"success": false, "message": "...", "error_code": "..."}

Error codes:
- VALIDATION_ERROR
- INGREDIENT_NOT_FOUND
- PRODUCT_NOT_FOUND
- API_ERROR

---

## 6. Deployment Checklist

- [ ] php artisan wol-ee:generate-bot-token --tenant=1
- [ ] Copy token ke bot config
- [ ] Test: POST /api/transactions manually (curl)
- [ ] Test: Full flow via Telegram bot
- [ ] Verify: Data muncul di dashboard

---

*Created: 18 June 2026*
*Author: Sena*
