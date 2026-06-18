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
    ├─ Pending clarification / batch reply? → handle pending
    │
    ├─ QUERY router (tanpa LLM) ─────────────────────────┐
    │     report_pnl / report_today / stock_alerts /     │
    │     margin_alerts / capabilities                     │
    │                                                    ▼
    ├─ Keyword sync (stok, history, partners, aging)    API + format
    │
    └─ ACTION planner → skill registry → cek kuota AI → Groq/DeepSeek → intent + slots
```

**Prinsip:** Query laporan **tidak** mengurangi kuota AI harian.

Skill registry statis hidup di `bot/skills.json`. Super Admin bisa melihat daftar skill,
required slots, tool target, contoh input, dan confirmation policy di `/platform/bot-skills`.

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
| `record_expense` | `POST /api/expenses` | "bayar listrik bulan ini 1.5jt", "bayar gaji 15jt" | ✅ |
| `record_*` | existing | beli/jual/batch; preview wajib sebelum mutasi | ✅ |

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
- AI action planner hanya menghasilkan intent + slot. Eksekusi tetap lewat validasi handler dan API Laravel.
- Semua mutasi dari natural language (`sale`, `purchase`, `expense`) wajib preview + konfirmasi. Jika user menyebut `total` penjualan, backend memakai total itu sebagai revenue aktual.

### 4.2 API

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/bot/usage?telegram_user_id=` | Sisa kuota, limit, plan, `uses_premium_llm` |
| POST | `/api/bot/ai-usage` | Body `{telegram_user_id}` — consume 1; 429 jika habis |
| POST | `/api/bot/ai-requests` | Event log LLM: provider, model, status, latency, token usage |
| POST | `/api/expenses` | Catat biaya operasional dari bot |

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
- Migration: `bot_feedbacks` (`tenant_id`, `telegram_user_id`, `original_message`, `feedback_text`, `status`, `note`)
- Service: `BotUsageService`
- Controller: `BotUsageController` atau extend `BotAuthController`
- Controller: `BotAiRequestController::store` (`POST /api/bot/ai-requests`)
- Controller: `BotFeedbackController::store` (`POST /api/bot/feedback`)
- Controller: `Api\ExpenseController::store` (`POST /api/expenses`)
- Report: `ReportController::pnl`, `::marginAlerts`, `::stockAlerts`
- Config: `config/ai.php` untuk quota produk dan batas provider (`rpm_limit`, `rpd_limit`)

---

## 6. Bot (Python)

| File | Peran |
|------|--------|
| `skills.json` | Registry statis skill bot (source untuk prompt + Super Admin page) |
| `skill_registry.py` | Loader registry untuk AI planner dan slot validation |
| `query_router.py` | Klasifikasi QUERY tanpa LLM |
| `handlers.py` | Handler PnL, stock/margin alerts, capabilities, feedback, event log AI |
| `wol_ee_client.py` | Client endpoint baru |
| `wol_ee_bridge.py` | QUERY sebelum ACTION |
| `bot_storage.py` | Kolom `tenant_plan` |
| `ai_parser.py` | Action planner intent + slot, metadata provider/model/status/latency/token dari `_call_llm` |

---

## 7. Feedback collection

Fallback unknown command wajib mengarahkan user ke `feedback <kebutuhan>`. Feedback disimpan untuk kurasi, bukan janji otomatis masuk roadmap. Status kurasi: `new`, `reviewed`, `planned`, `shipped`, `rejected`.

---

## 8. Out of scope (demo)

- Tax simulation via bot
- Compare period
- Billing / payment gateway
- Super admin monitoring non-AI (`api_calls`, active users, error logs)

---

*Last updated: 17 June 2026*
