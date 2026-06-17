"""Bridge antara bot Telegram (bot.py) dan modul Wol-ee API."""

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


def is_wolee_user(user_id: int) -> bool:
    return _storage.is_registered(user_id)


def try_handle(user_id: int, text: str) -> str | None:
    """Sync handler untuk command/keyword."""
    normalized = text.strip().lower()
    clean = normalized.lstrip("/")

    if normalized.startswith("/start ") or clean.startswith("start "):
        return _handlers.handle_start(user_id, text)

    if not is_wolee_user(user_id):
        return None

    if clean in {"summary", "profit", "ringkasan"} or "profit hari ini" in normalized:
        return _handlers.handle_report_today(user_id)

    if clean in {"history", "riwayat"}:
        return _handlers.handle_history(user_id)

    if clean in {"partners", "partner", "daftar partner"}:
        return _handlers.handle_partners(user_id)

    if clean.startswith("beli "):
        return _handlers.handle_pembelian(user_id, text)

    if clean.startswith("jual "):
        return _handlers.handle_penjualan(user_id, text)

    if clean.startswith("stok") or clean == "cek stok":
        return _handlers.handle_stok(user_id, text)

    if clean in {"aging", "cek aging", "laporan aging"}:
        return _handlers.handle_aging(user_id)

    if normalized in {"bantuan", "/bantuan", "help wolee"}:
        return (
            "Perintah Wol-ee:\n"
            "• Beli tepung Rp 200 ribu — catat pembelian (NL)\n"
            "• Jual matcha latte 10 — catat penjualan\n"
            "• stok [bahan] — cek stok\n"
            "• /profit — laporan hari ini\n"
            "• /history — riwayat transaksi\n"
            "• /partners — daftar partner\n"
            "• aging — laporan piutang\n"
            "/start <token> — daftar tenant"
        )

    return None


async def handle_wolee_message(user_id: int, text: str, is_pro: bool = False) -> str | None:
    """Async NL handler untuk pesan bebas. Hanya untuk user terdaftar Wol-ee."""
    if not is_wolee_user(user_id):
        return None

    if try_handle(user_id, text) is not None:
        return try_handle(user_id, text)

    return await _handlers.handle_natural_language(user_id, text, is_pro=is_pro)
