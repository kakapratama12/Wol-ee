"""Bridge antara bot Telegram (bot.py) dan modul Wol-ee API."""

from __future__ import annotations

from pathlib import Path

from bot_response import BotResponse
from bot_storage import BotUserStorage
from config import config
from handlers import BotHandlers
from offline_queue import OfflineQueue
from pending_batch import PendingBatchStorage
from query_router import classify_query

_data = Path(__file__).parent / "data"
_storage = BotUserStorage(_data / "bot_users.db")
_queue = OfflineQueue(_data / "offline_queue.db")
_pending = PendingBatchStorage(_data / "pending_batches.db")
_handlers = BotHandlers(
    api_url=config.WOL_EE_API_URL,
    default_token=config.WOL_EE_API_TOKEN,
    storage=_storage,
    queue=_queue,
    pending=_pending,
)


def is_wolee_user(user_id: int) -> bool:
    return _storage.is_registered(user_id)


def try_handle(user_id: int, text: str) -> str | BotResponse | None:
    """Sync handler untuk command/keyword."""
    normalized = text.strip().lower()
    clean = normalized.lstrip("/")

    if normalized.startswith("/start ") or clean.startswith("start "):
        return _handlers.handle_start(user_id, text)

    if not is_wolee_user(user_id):
        return None

    if clean.startswith("feedback "):
        return _handlers.handle_feedback(user_id, text)

    if clean in {"profit", "ringkasan hari ini"} or "profit hari ini" in normalized or "omset hari ini" in normalized:
        return _handlers.handle_report_today(user_id)

    if clean in {"summary", "ringkasan"}:
        return _handlers.handle_report_pnl(user_id, *_current_month_year())

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
        return _handlers.handle_capabilities(user_id)

    return None


def _current_month_year() -> tuple[int, int]:
    from datetime import datetime

    now = datetime.now()
    return now.month, now.year


async def handle_wolee_message(user_id: int, text: str, is_pro: bool = False) -> str | BotResponse | None:
    """Async NL handler untuk pesan bebas. Hanya untuk user terdaftar Wol-ee."""
    if not is_wolee_user(user_id):
        return None

    pending_reply = _handlers.handle_pending_reply(user_id, text)
    if pending_reply is not None:
        return pending_reply

    if text.strip().lower().lstrip("/").startswith("feedback "):
        return _handlers.handle_feedback(user_id, text)

    query_kind = classify_query(text)
    if query_kind is not None:
        return _handlers.handle_query(user_id, text, query_kind)

    sync_reply = try_handle(user_id, text)
    if sync_reply is not None:
        return sync_reply

    return await _handlers.handle_natural_language(user_id, text, is_pro=is_pro)


def handle_callback_query(user_id: int, callback_data: str) -> str | BotResponse:
    return _handlers.handle_callback_query(user_id, callback_data)
