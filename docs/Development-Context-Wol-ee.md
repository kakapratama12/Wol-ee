# Wol-ee: Development Context

## 1. Existing System

### 1.1 Keuangan Bot (Sudah Ada)

**Stack:**
- Python + PostgreSQL
- Telegram Bot (Baileys/telebot)
- LLM: Groq (free) / OpenRouter DeepSeek V4 Flash (pro)
- VPS: Hermes (43.157.197.127)
- Path: /home/ubuntu/keuangan-bot/

**Fitur yang sudah ada:**
- NL → Transaction parsing
- Auto AR (detect lunas → cash)
- Payment flow
- Partner auto-create + management
- User ID filtering
- Timezone: WIB (GMT+7)

**Yang perlu ditambah (untuk Wol-ee):**
- Inventory tracking (stok bahan baku)
- Recipe management (resep + gramasi)
- COGS calculation (auto-hitung dari resep)
- Margin tracking
- Integration ke Laravel API (dashboard)

### 1.2 Laravel Dashboard (Belum Ada)

**Stack:**
- Laravel + Blade/Livewire
- PostgreSQL (shared dengan bot)
- Deploy: VPS Nawasena (178.128.23.108) atau VPS baru

**Yang perlu dibangun:**
- Dashboard overview
- Inventory management
- Recipe management
- P&L report
- Tax simulator
- Margin protection
- User authentication (Owner/Admin)

---

## 2. Architecture

```
┌─────────────────┐     ┌─────────────────┐
│  📱 Telegram    │────▶│  🐍 Bot (EXIST) │
│  (User Input)   │◀────│  Python         │
└─────────────────┘     └────────┬────────┘
                                 │
                          ┌──────▼──────┐
                          │  🔌 API     │
                          │  (Laravel)  │
                          │  [NEW]      │
                          └──────┬──────┘
                                 │
                    ┌────────────┼────────────┐
                    │            │            │
              ┌─────▼─────┐ ┌───▼───┐
              │  💾 DB    │ │ 🖥️ Web│
              │ (Postgres)│ │ (NEW) │
              │ [EXIST]   │ │       │
              └───────────┘ └───────┘
```

### 2.1 Integration Points

**Bot → Laravel API:**
- Bot save transaksi → call Laravel API
- Laravel API update DB → return response ke bot
- Endpoint: POST /api/transactions, POST /api/sales

**Dashboard → DB:**
- Laravel langsung query DB
- Tampilkan data real-time
- User bisa input/edit via form

---

## 3. Business Rules

### 3.1 Inventory

**Unit Types:**
- Bahan Baku (Gramasi): kg, gram, liter, butir
- Bahan Baku (Packaged): sachet, pack, botol
- Produk Jadi: pcs, cup, biji, porsi

**Stock Flow:**
```
Beli Tepung 5kg (Rp 100.000)
→ Stok Tepung: 5kg

Jual Matcha 10 biji
→ Estimasi terpakai: 1kg (100g × 10)
→ Stok Tepung: 4kg
```

**Alert Threshold:**
- ✅ Aman: Stok > minimum
- ⚠️ Menipis: Stok < minimum
- 🔴 Kritis: Stok < 50% minimum

### 3.2 COGS Calculation

**Formula:**
```
COGS per porsi = Σ (Gramasi per porsi × Harga per gram)
```

**Contoh:**
```
Matcha Latte:
- Susu: 200ml × Rp 18.000/L = Rp 3.600
- Matcha: 20g × Rp 250.000/kg = Rp 5.000
- Gula: 15g × Rp 15.000/kg = Rp 225
Total COGS: Rp 8.825

Harga Jual: Rp 45.000
Profit: Rp 36.175 (80.4%)
```

**Update otomatis:**
- Saat harga bahan baku berubah → COGS otomatis update
- Saat resep diubah → COGS otomatis recalculate

### 3.3 Waste Adjustment

**Legitimate waste percentage:**
- Tepung: 5-10% (spillage, rusak)
- Susu: 3-5% (tumpah, expired)
- Telur: 2-3 butir (pecah)
- Kopi: 2-5% (spillage)

**Cara hitung:**
```
COGS dengan waste = COGS × (1 + waste%)
Contoh: 8.825 × 1.15 = Rp 10.149 (dengan waste 15%)
```

### 3.4 Tax Simulator

**Input:**
- Tipe bisnis: Perorangan / CV / PT
- Omset: dari transaksi
- COGS: dari tracking (atau manual)
- Expense lain: listrik, sewa, internet, dll
- Waste %: user input (default 10%)

**Logic:**

**PP 23/2018 (Final):**
```
Pajak = 0.5% × Omset
```

**Normal Taxation:**
```
Profit Taxable = Omset - COGS - Expense
Pajak = Profit Taxable × Rate

Rate berdasarkan tipe:
- Perorangan: PPh 21 (progresif 5-35%)
- CV/PT Badan: 22%
```

**Output:**
- Estimasi pajak PP 23
- Estimasi pajak Normal
- Selisih (hemat/lebih)

### 3.5 Margin Protection

**Price Tracker:**
- Store harga bahan baku historical
- Update saat user input pembelian baru

**Margin Alert:**
- Trigger: Margin turun > 2% dari bulan lalu
- Notification ke bot + dashboard

**What-If Simulator:**
```
Input: Kenaikan harga X%
Output:
- COGS baru
- Margin baru
- Saran kenaikan harga jual
```

**Price Recommendation:**
```
Target margin: 80%
Current margin: 78%
Required price increase: (80 - 78) / 78 = 2.56%
```

### 3.6 P&L Report

**Structure:**
```
Revenue:        Rp XX
COGS:          (Rp XX)
Gross Profit:   Rp XX

Expenses:
- Listrik:     (Rp XX)
- Sewa:        (Rp XX)
- Internet:    (Rp XX)
- Lainnya:     (Rp XX)
Total Expenses: (Rp XX)

Laba Bersih:    Rp XX
```

**Export:**
- Format: Excel (.xlsx)
- Include: Semua data di atas
- User bisa edit setelah export

---

## 4. Data Relationships (High Level)

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  SUPPLIERS  │     │  INGREDIENTS│     │  PRODUCTS   │
├─────────────┤     ├─────────────┤     ├─────────────┤
│ id          │     │ id          │     │ id          │
│ name        │     │ name        │     │ name        │
│ contact     │     │ unit_type   │     │ price       │
│             │     │ unit_price  │     │             │
│             │     │ supplier_id │     │             │
└─────────────┘     └──────┬──────┘     └──────┬──────┘
                           │                   │
                           │  ┌─────────────┐  │
                           └──│  RECIPES    │──┘
                              ├─────────────┤
                              │ id          │
                              │ product_id  │
                              │ ingredient_id│
                              │ quantity    │
                              │ unit        │
                              └─────────────┘

┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│ TRANSACTIONS│     │    SALES    │     │   STOCK     │
├─────────────┤     ├─────────────┤     ├─────────────┤
│ id          │     │ id          │     │ id          │
│ type        │     │ product_id  │     │ ingredient_id│
│ item        │     │ quantity    │     │ quantity    │
│ quantity    │     │ price       │     │ last_updated│
│ unit_price  │     │ cogs        │     │ minimum     │
│ total       │     │ profit      │     │             │
│ date        │     │ date        │     │             │
└─────────────┘     └─────────────┘     └─────────────┘

┌─────────────┐     ┌─────────────┐
│ PRICE_HISTORY│    │   USERS     │
├─────────────┤     ├─────────────┤
│ id          │     │ id          │
│ ingredient_id│    │ name        │
│ price       │     │ email       │
│ date        │     │ role        │
│             │     │ (owner/admin)│
└─────────────┘     └─────────────┘
```

---

## 5. API Endpoints (Proposed)

### 5.1 Auth
- POST /api/login
- POST /api/register

### 5.2 Ingredients
- GET /api/ingredients
- POST /api/ingredients
- PUT /api/ingredients/{id}
- DELETE /api/ingredients/{id}
- GET /api/ingredients/{id}/price-history

### 5.3 Products
- GET /api/products
- POST /api/products
- PUT /api/products/{id}
- DELETE /api/products/{id}

### 5.4 Recipes
- GET /api/recipes
- POST /api/recipes
- PUT /api/recipes/{id}
- DELETE /api/recipes/{id}

### 5.5 Transactions
- GET /api/transactions
- POST /api/transactions
- PUT /api/transactions/{id}
- DELETE /api/transactions/{id}

### 5.6 Sales
- GET /api/sales
- POST /api/sales
- PUT /api/sales/{id}
- DELETE /api/sales/{id}

### 5.7 Reports
- GET /api/reports/pnl?month=&year=
- GET /api/reports/cashflow?month=&year=
- GET /api/reports/export/pnl?month=&year=

### 5.8 Tax
- GET /api/tax/simulator?omset=&cogs=&expense=&waste=&type=

### 5.9 Margin
- GET /api/margin/alerts
- GET /api/margin/what-if?product_id=&increase%

---

## 6. Bot Integration

### 6.1 Bot → API Call

```python
# Contoh: User beli bahan
def handle_buy(message):
    # Parse NL
    item = parse_item(message)
    qty = parse_quantity(message)
    price = parse_price(message)
    
    # Call Laravel API
    response = requests.post(
        f"{API_URL}/api/transactions",
        json={
            'item': item,
            'quantity': qty,
            'unit_price': price,
            'type': 'purchase'
        }
    )
    
    # Return response ke user
    if response.status_code == 200:
        return f"✅ Tercatat! Stok {item}: {response.json()['new_stock']}"
```

### 6.2 Bot → DB Direct (Existing)

```python
# Existing: User lihat stok
def handle_check_stock(message):
    # Langsung query DB
    stocks = db.query("SELECT * FROM stock")
    
    # Format response
    response = "📦 Stok Hari Ini:\n"
    for stock in stocks:
        status = "✅" if stock.quantity > stock.minimum else "⚠️"
        response += f"{stock.name}: {stock.quantity} {status}\n"
    
    return response
```

---

## 7. Deployment

### 7.1 Bot (Existing)
- VPS: Hermes (43.157.197.127)
- Path: /home/ubuntu/keuangan-bot/
- Start: cd /home/ubuntu/keuangan-bot && source venv/bin/activate && python bot.py

### 7.2 Dashboard (New)
- VPS: Nawasena (178.128.23.108) atau VPS baru
- Stack: Laravel + Nginx
- Path: /var/www/wol-ee/

### 7.3 Database
- Shared: PostgreSQL di Hermes
- Or: Separate DB di VPS dashboard

---

## 8. Notes for Developer

1. **Bot sudah ada** — jangan buat dari scratch, tambah fitur aja
2. **Database bisa shared** atau pisah, sesuaikan dengan scalability
3. **API harus RESTful** — biar bot dan dashboard bisa akses
4. **Gunakan Laravel best practices** — Repository pattern, Service layer
5. **Database schema flexible** — developer bisa define sendiri sesuai kebutuhan
6. **Focus di business logic** — COGS calculation, waste, tax simulator
7. **Export Excel** — gunakan library seperti PhpSpreadsheet

---

*Document version: 0.1*
*Last updated: 16 June 2026*
*Author: Sena (AI Assistant)*
