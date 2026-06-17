# Wol-ee Telegram Bot — Integrasi API

Modul Python di-copy ke server bot (`/home/ubuntu/keuangan-bot/`). **Tidak mengganti `bot.py`** — hanya menambah routing fork untuk user Wol-ee terdaftar.

## Arsitektur

```
bot.py (shell: Telegram, rate limit, backup — tidak diubah)
  ├─ User terdaftar Wol-ee → wol_ee_bridge → ai_parser (NL) → wol_ee_client → Laravel API
  └─ User lain → flow legacy + local DB (tidak berubah)
```

## File

| File | Fungsi |
|------|--------|
| `ai_parser.py` | NL parsing: legacy `parse_transaction` + Wol-ee `parse_wolee_inventory` |
| `wol_ee_client.py` | HTTP client ke Laravel API (CRUD + read endpoints) |
| `handlers.py` | Handler pembelian, penjualan, stok, laporan, history, partners |
| `wol_ee_bridge.py` | Routing fork: `is_wolee_user`, `try_handle`, `handle_wolee_message` |
| `bot_storage.py` | Registrasi staff via `/start <token>` |
| `offline_queue.py` | Antrian saat API timeout |
| `patch_vps_runtime.py` | Patch minimal `bot.py` di VPS |
| `deploy_to_vps.sh` | Deploy modul + restart supervisor |

## Deploy

```bash
# Dari repo lokal
cd bot && ./deploy_to_vps.sh

# Atau manual
scp ai_parser.py handlers.py wol_ee_bridge.py wol_ee_client.py bot_storage.py offline_queue.py ubuntu@VPS:/home/ubuntu/keuangan-bot/
ssh ubuntu@VPS "cd /home/ubuntu/keuangan-bot && source venv/bin/activate && pip install -r requirements.txt && python3 patch_vps_runtime.py && sudo supervisorctl restart keuangan-bot"
```

## Env VPS

```
WOL_EE_API_URL=http://127.0.0.1/api
WOL_EE_API_TOKEN={tenant_id}:{secret}   # dari dashboard Settings > Bot Integration
GROQ_API_KEY=...                         # existing
OPENROUTER_API_KEY=...                   # existing
```

## Perintah Wol-ee (user terdaftar)

| Input | Aksi |
|-------|------|
| `/start 1:token` | Daftar tenant |
| `Beli tepung Rp 200 ribu` | NL → POST /api/transactions |
| `Jual matcha latte 10` | NL → POST /api/sales |
| `stok tepung` | GET /api/stock |
| `/profit` | GET /api/reports/today |
| `/history` | GET /api/sales + /api/transactions |
| `/partners` | GET /api/partners |
| `aging` | GET /api/reports/aging |

## Token Format

```
{tenant_id}:{secret}
Header: Authorization: Bearer 1:abc123def456
```
