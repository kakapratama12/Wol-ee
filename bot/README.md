# Wol-ee Telegram Bot — Integrasi API

Modul Python ini di-copy ke server bot (`/home/ubuntu/keuangan-bot/`) atau dipakai sebagai referensi integrasi.

## File

| File | Fungsi |
|------|--------|
| `config.example.py` | Template konfigurasi API URL & token |
| `wol_ee_client.py` | HTTP client ke Laravel API |
| `bot_storage.py` | Registrasi staff via `/start <token>` |
| `offline_queue.py` | Antrian saat API timeout |
| `handlers.py` | Handler pembelian, penjualan, stok, aging |

## Setup

```bash
cp config.example.py config.py
# Edit WOL_EE_API_URL dan WOL_EE_API_TOKEN
pip install -r requirements.txt
```

## Deployment Checklist

- [ ] `php artisan wol-ee:generate-bot-token --tenant=1` (atau via dashboard Settings > Bot Integration)
- [ ] Copy token ke `config.py` → `WOL_EE_API_TOKEN`
- [ ] Set `WOL_EE_API_URL` ke domain produksi (mis. `http://43.157.240.175/api`)
- [ ] Test manual: `curl -H "Authorization: Bearer 1:secret" https://domain/api/stock`
- [ ] Copy modul `bot/` ke server keuangan-bot
- [ ] Restart bot: `supervisorctl restart keuangan-bot`
- [ ] Test Telegram: `/start 1:token` lalu `beli tepung 2kg 36000`
- [ ] Verifikasi data muncul di dashboard Wol-ee

## Token Format

```
{tenant_id}:{secret}
Contoh: 1:abc123def456ghi789
```

Header: `Authorization: Bearer 1:abc123def456ghi789`

## Error Handling

- **Timeout / koneksi gagal** → simpan ke `offline_queue`, balas "Tersimpan offline"
- **INGREDIENT_NOT_FOUND** → tampilkan suggestions dari API
- **VALIDATION_ERROR** → tampilkan pesan validasi
