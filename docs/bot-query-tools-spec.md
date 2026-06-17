# Bot Query Tools & AI Quota — Spec

> **Status:** Implementing (demo-ready)
> **Prioritas:** P1 — owner tidak anggap bot "bodoh" sebelum demo early adopter

---

## 1. Tujuan

Bot Wol-ee untuk **owner** harus bisa:

1. **Baca laporan** dari data tenant (tanpa LLM — murah, cepat)
2. **Pantau masalah** (stok menipis, margin turun)
3. **Jawab meta** ("bisa nanya apa?")
4. **Input transaksi** (sudah ada — pakai LLM, kena kuota)

**Pajak** sengaja **bukan** bagian demo bot — tetap di dashboard, on-demand.

---

## 2. Arsitektur routing

```
Pesan masuk (user Wol-ee terdaftar)
    │
    ├─ Pending batch reply? → handle pending
    │
    ├─ QUERY router (tanpa LLM) ─────────────────────────┐
    │     report_pnl / report_today / stock_alerts /     │
    │     margin_alerts / capabilities                     │
    │                                                    ▼
    ├─ Keyword sync (beli, jual, stok, history, …)      API + format
    │
    └─ ACTION (NL inventory) → cek kuota AI → Groq/DeepSeek → parser
```

**Prinsip:** Query laporan **tidak** mengurangi kuota AI harian.

---

## 3. Tools (Tier 1 demo)

| Tool | API | Contoh NL | LLM? |
|------|-----|-----------|------|
| `get_pnl` | `GET /api/reports/pnl?month=&year=` | "profit bulan ini", "ringkasan Juni" | ❌ |
| `get_report_today` | `GET /api/reports/today` | "omset hari ini", "hari ini gimana" | ❌ |
| `get_stock_alerts` | `GET /api/reports/stock-alerts` | "stok menipis", "ada yang kritis" | ❌ |
| `get_margin_alerts` | `GET /api/reports/margin-alerts` | "margin turun", "produk boncos" | ❌ |
| `get_top_products` | `GET /api/reports/top-products?month=&year=` | "barang paling laku", "produk terlaris" | ❌ |
| `get_bottom_products` | `GET /api/reports/bottom-products?month=&year=` | "barang paling ga laku", "produk sepi" | ❌ |
| `business_insight` | PnL + top/bottom + alerts | "strategi kedepannya", "saran dong" | ❌ |
| `explain_capabilities` | bot-only | "bisa nanya apa", "kamu bisa apa" | ❌ |
| `record_*` | existing | beli/jual/batch | ✅ |

**Perbaikan:** `/summary` dan "ringkasan" → PnL **bulan ini** (bukan hari ini).

---

## 4. AI quota & LLM tier

### 4.1 Kuota harian (per `telegram_user_id` + `tenant_id`)

| Plan (`tenants.plan`) | LLM | Kuota AI/hari |
|------------------------|-----|---------------|
| `free` | Groq | **25** |
| `pro` | DeepSeek (OpenRouter) | 150 |
| `business` | DeepSeek (OpenRouter) | 150 |

- Reset: **00:00 WIB** (timezone `Asia/Jakarta`)
- Hanya path yang memanggil LLM (`parse_wolee_inventory`, dll.) yang `consume`
- Query router & keyword sync **tidak** consume

### 4.2 API

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/bot/usage?telegram_user_id=` | Sisa kuota, limit, plan, `uses_premium_llm` |
| POST | `/api/bot/ai-usage` | Body `{telegram_user_id}` — consume 1; 429 jika habis |

`POST /api/bot/validate-token` response tenant menyertakan `plan` (sudah ada).

### 4.3 Pesan limit

```
⚠️ Kuota AI hari ini habis (25/25).
Reset besok jam 00:00 WIB.
Upgrade ke Pro untuk kuota lebih besar & respons lebih akurat.
```

### 4.4 Sumber tier LLM

`is_pro` untuk Wol-ee path = `tenants.plan` ∈ `{pro, business}` (disimpan saat `/start`),
**bukan** legacy `User.plan` di DB keuangan-bot.

---

## 5. Laravel

- Migration: `bot_ai_usages` (`tenant_id`, `telegram_user_id`, `usage_date`, `count`)
- Service: `BotUsageService`
- Controller: `BotUsageController` atau extend `BotAuthController`
- Report: `ReportController::pnl`, `::marginAlerts`, `::stockAlerts`

---

## 6. Bot (Python)

| File | Peran |
|------|--------|
| `query_router.py` | Klasifikasi QUERY tanpa LLM |
| `handlers.py` | Handler PnL, stock/margin alerts, capabilities |
| `wol_ee_client.py` | Client endpoint baru |
| `wol_ee_bridge.py` | QUERY sebelum ACTION |
| `bot_storage.py` | Kolom `tenant_plan` |
| `ai_parser.py` | `consume` kuota sebelum `_call_llm` |

---

## 7. Out of scope (demo)

- Tax simulation via bot
- Top products, compare period
- Billing / payment gateway
- Super admin monitoring (`api_calls`/AI usage analytics — Sprint 5): token/LLM usage, request per minute, quota consumption per tenant dan aggregate

---

*Last updated: 17 June 2026*
