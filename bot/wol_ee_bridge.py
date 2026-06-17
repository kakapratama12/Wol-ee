"""Bridge antara bot Telegram dan modul Wol-ee API."""

from __future__ import annotations

from pathlib import Path

from bot_storage import BotUserStorage
from config import config
from handlers import BotHandlers
from offline_queue import OfflineQueue

_data = Path(__file__).parent / "data"
_storage = BotUserStorage(_data / "bot_users.db")
_queue = OfflineQueue(_data / "offline_queue.db")
_handlers = BotHandlers(
    api_url=config.WOL_EE_API_URL,
    default_token=config.WOL_EE_API_TOKEN,
    storage=_storage,
    queue=_queue,
)


def try_handle(user_id: int, text: str) -> str | None:
    normalized = text.strip().lower()
    clean = normalized.lstrip("/")

    if normalized.startswith("/start ") or clean.startswith("start "):
        return _handlers.handle_start(user_id, text)

    if clean.startswith("beli "):
        return _handlers.handle_pembelian(user_id, text)

    if clean.startswith("jual "):
        return _handlers.handle_penjualan(user_id, text)

    if clean.startswith("stok"):
        return _handlers.handle_stok(user_id, text)

    if clean in {"aging", "cek aging", "laporan aging"}:
        return _handlers.handle_aging(user_id)

    if normalized in {"bantuan", "/bantuan", "help wolee"}:
        return (
            "Perintah Wol-ee:\n"
            "• beli <bahan> <qty> <total> — catat pembelian\n"
            "• jual <produk> <qty> — catat penjualan\n"
            "• stok [bahan] — cek stok\n"
            "• aging — laporan piutang\n"
            "/start <token> — daftar tenant"
        )

    return None
