# Wol-ee Telegram Bot — Integrasi API

Modul Python di-copy ke server bot (`/home/ubuntu/keuangan-bot/`). **Tidak mengganti `bot.py`** — hanya menambah routing fork untuk user Wol-ee terdaftar.

## Arsitektur

```
bot.py (shell: Telegram, rate limit, backup — tidak diubah)
  ├─ User terdaftar Wol-ee → wol_ee_bridge
  │     ├─ query_router (laporan/pantau — TANPA LLM, tanpa kuota AI)
  │     ├─ keyword sync (stok, history, partners, aging)
  │     └─ ai_parser (intent + slot action planner — pakai LLM, kena kuota AI)
  └─ User lain → flow legacy + local DB (tidak berubah)
```

## File

| File | Fungsi |
|------|--------|
| `query_router.py` | Klasifikasi pertanyaan laporan/pantau tanpa LLM |
| `ai_parser.py` | AI action planner: intent + slot untuk transaksi/biaya |
| `wol_ee_client.py` | HTTP client ke Laravel API |
| `handlers.py` | Handler transaksi, laporan, alert, history, slot validation |
| `wol_ee_bridge.py` | Routing: pending → QUERY → sync → AI ACTION |
| `bot_storage.py` | Registrasi staff + `tenant_plan` |
| `offline_queue.py` | Antrian saat API timeout |
| `patch_vps_runtime.py` | Patch minimal `bot.py` di VPS |
| `deploy_to_vps.sh` | Deploy modul + restart supervisor |

## Deploy

```bash
cd bot && ./deploy_to_vps.sh
```

## Env VPS

```
WOL_EE_API_URL=https://wolee.my.id/api
WOL_EE_API_TOKEN={tenant_id}:{secret}
WOL_EE_APP_URL=https://wolee.my.id
GROQ_API_KEY=...
OPENROUTER_API_KEY=...
```

## Perintah Wol-ee (user terdaftar)

### Laporan & pantau (tanpa kuota AI)

| Input | Aksi |
|-------|------|
| `profit bulan ini` / `ringkasan` / `/summary` | GET /api/reports/pnl |
| `omset hari ini` / `/profit` | GET /api/reports/today |
| `stok menipis` / `stok kritis` | GET /api/reports/stock-alerts |
| `margin turun` | GET /api/reports/margin-alerts |
| `barang paling laku` | GET /api/reports/top-products |
| `barang paling ga laku` | GET /api/reports/bottom-products |
| `strategi kedepannya` | PnL + top/bottom products + stock/margin alerts |
| `bisa nanya apa` / `bantuan` | Daftar kemampuan bot |

### Transaksi (pakai kuota AI untuk NL kompleks)

| Input | Aksi |
|-------|------|
| `/start 1:token` | Daftar tenant |
| `Beli tepung Rp 200 ribu` | NL → preview → POST /api/transactions |
| `Jual matcha latte 10` | NL → preview → POST /api/sales |
| `kopi susu 50 total 10jt` | NL → preview harga aktual → POST /api/sales |
| `bayar listrik bulan ini 1.5jt` | NL → preview → POST /api/expenses |
| `bayar mbok Ijah buat beli telur 2kg` | NL → tanya nominal dulu |
| Copas batch multi-item | NL batch + konfirmasi |
| `stok tepung` | GET /api/stock |
| `/history` | Riwayat transaksi |
| `/partners` | Daftar partner |
| `aging` | Laporan piutang |

## Kuota AI

| Plan tenant | LLM | Limit/hari |
|-------------|-----|------------|
| free | Groq | 25 |
| pro / business | DeepSeek (OpenRouter) | 150 |

- Hanya pesan yang memanggil LLM yang mengurangi kuota
- Query laporan & keyword sync **tidak** mengurangi kuota
- Reset 00:00 WIB
- Tier LLM dari `tenants.plan` Wol-ee (bukan legacy DB bot)

Spec lengkap: [docs/bot-query-tools-spec.md](../docs/bot-query-tools-spec.md)

## Token Format

```
{tenant_id}:{secret}
Header: Authorization: Bearer 1:abc123def456
```
