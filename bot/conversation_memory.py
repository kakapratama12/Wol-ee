"""Conversation Memory — SQLite-backed context for Wol-ee bot.

Stores last N conversations per user so the bot can reference
previous exchanges when interpreting reports.

Schema:
    conversation_history (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        role TEXT NOT NULL,       -- 'user' or 'bot'
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )

Retention: last 5 exchanges (10 messages) per user, auto-purged.
"""

import sqlite3
import threading
from pathlib import Path
from datetime import datetime, timedelta

_DEFAULT_DB = Path(__file__).parent / "data" / "conversation_memory.db"
_MAX_EXCHANGES = 5  # last 5 user+bot pairs = 10 messages
_RETENTION_DAYS = 7  # auto-purge messages older than 7 days


class ConversationMemory:
    """Thread-safe conversation memory backed by SQLite."""

    def __init__(self, db_path: str | Path | None = None):
        self._db_path = str(db_path or _DEFAULT_DB)
        Path(self._db_path).parent.mkdir(parents=True, exist_ok=True)
        self._local = threading.local()
        self._init_db()

    def _get_conn(self) -> sqlite3.Connection:
        if not hasattr(self._local, "conn") or self._local.conn is None:
            self._local.conn = sqlite3.connect(self._db_path)
            self._local.conn.row_factory = sqlite3.Row
        return self._local.conn

    def _init_db(self):
        conn = self._get_conn()
        conn.execute("""
            CREATE TABLE IF NOT EXISTS conversation_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role TEXT NOT NULL CHECK (role IN ('user', 'bot')),
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)
        conn.execute("""
            CREATE INDEX IF NOT EXISTS idx_conv_user_time
            ON conversation_history (user_id, created_at DESC)
        """)
        conn.commit()

    def store(self, user_id: int, role: str, message: str):
        """Store a message in conversation history."""
        conn = self._get_conn()
        conn.execute(
            "INSERT INTO conversation_history (user_id, role, message) VALUES (?, ?, ?)",
            (user_id, role, message),
        )
        conn.commit()
        self._trim(user_id)

    def get_context(self, user_id: int, n_exchanges: int = _MAX_EXCHANGES) -> list[dict]:
        """Get last N exchanges for context injection.

        Returns list of {'role': 'user'|'bot', 'message': str} in chronological order.
        """
        conn = self._get_conn()
        limit = n_exchanges * 2  # each exchange = user + bot message
        rows = conn.execute(
            """
            SELECT role, message FROM conversation_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
            """,
            (user_id, limit),
        ).fetchall()
        # Reverse to chronological order
        return [{"role": r["role"], "message": r["message"]} for r in reversed(rows)]

    def _trim(self, user_id: int):
        """Keep only last N exchanges, purge old messages."""
        conn = self._get_conn()
        # Get the cutoff ID (keep last N*2 messages)
        cutoff = conn.execute(
            """
            SELECT id FROM conversation_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1 OFFSET ?
            """,
            (user_id, _MAX_EXCHANGES * 2),
        ).fetchone()

        if cutoff:
            conn.execute(
                "DELETE FROM conversation_history WHERE user_id = ? AND id < ?",
                (user_id, cutoff["id"]),
            )
            conn.commit()

    def purge_old(self, days: int = _RETENTION_DAYS):
        """Purge messages older than N days (call periodically)."""
        conn = self._get_conn()
        cutoff = (datetime.utcnow() - timedelta(days=days)).isoformat()
        conn.execute(
            "DELETE FROM conversation_history WHERE created_at < ?",
            (cutoff,),
        )
        conn.commit()

    def clear(self, user_id: int):
        """Clear all history for a user."""
        conn = self._get_conn()
        conn.execute("DELETE FROM conversation_history WHERE user_id = ?", (user_id,))
        conn.commit()
