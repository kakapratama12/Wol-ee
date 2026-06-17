"""Penyimpanan registrasi user bot (telegram_user_id -> token)."""

from __future__ import annotations

import sqlite3
from datetime import datetime, timezone
from pathlib import Path

PREMIUM_PLANS = frozenset({"pro", "business"})


class BotUserStorage:
    def __init__(self, db_path: Path):
        self.db_path = db_path
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self._init_db()

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self.db_path)

    def _init_db(self) -> None:
        with self._connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS bot_users (
                    telegram_user_id INTEGER PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    token TEXT NOT NULL,
                    tenant_plan TEXT NOT NULL DEFAULT 'free',
                    registered_at TEXT NOT NULL,
                    last_active_at TEXT NOT NULL
                )
                """
            )
            columns = {
                row[1]
                for row in conn.execute("PRAGMA table_info(bot_users)").fetchall()
            }
            if "tenant_plan" not in columns:
                conn.execute(
                    "ALTER TABLE bot_users ADD COLUMN tenant_plan TEXT NOT NULL DEFAULT 'free'"
                )

    def register(
        self,
        telegram_user_id: int,
        tenant_id: int,
        token: str,
        tenant_plan: str = "free",
    ) -> None:
        now = datetime.now(timezone.utc).isoformat()
        with self._connect() as conn:
            conn.execute(
                """
                INSERT INTO bot_users (
                    telegram_user_id, tenant_id, token, tenant_plan, registered_at, last_active_at
                )
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(telegram_user_id) DO UPDATE SET
                    tenant_id = excluded.tenant_id,
                    token = excluded.token,
                    tenant_plan = excluded.tenant_plan,
                    last_active_at = excluded.last_active_at
                """,
                (telegram_user_id, tenant_id, token, tenant_plan, now, now),
            )

    def get_token(self, telegram_user_id: int) -> str | None:
        with self._connect() as conn:
            row = conn.execute(
                "SELECT token FROM bot_users WHERE telegram_user_id = ?",
                (telegram_user_id,),
            ).fetchone()
        return row[0] if row else None

    def get_tenant_plan(self, telegram_user_id: int) -> str:
        with self._connect() as conn:
            row = conn.execute(
                "SELECT tenant_plan FROM bot_users WHERE telegram_user_id = ?",
                (telegram_user_id,),
            ).fetchone()
        return row[0] if row else "free"

    def uses_premium_llm(self, telegram_user_id: int) -> bool:
        return self.get_tenant_plan(telegram_user_id) in PREMIUM_PLANS

    def touch(self, telegram_user_id: int) -> None:
        now = datetime.now(timezone.utc).isoformat()
        with self._connect() as conn:
            conn.execute(
                "UPDATE bot_users SET last_active_at = ? WHERE telegram_user_id = ?",
                (now, telegram_user_id),
            )

    def is_registered(self, telegram_user_id: int) -> bool:
        return self.get_token(telegram_user_id) is not None
