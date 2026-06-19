"""Pending batch confirmation state (SQLite)."""

from __future__ import annotations

import json
import sqlite3
import uuid
from datetime import datetime, timedelta, timezone
from pathlib import Path


class PendingBatchStorage:
    def __init__(self, db_path: Path, ttl_minutes: int = 15):
        self.db_path = db_path
        self.ttl_minutes = ttl_minutes
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self._init_db()

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self.db_path)

    def _init_db(self) -> None:
        with self._connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS pending_batches (
                    pending_id TEXT PRIMARY KEY,
                    telegram_user_id INTEGER NOT NULL,
                    kind TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    expires_at TEXT NOT NULL
                )
                """
            )

    def _purge_expired(self) -> None:
        now = datetime.now(timezone.utc).isoformat()
        with self._connect() as conn:
            conn.execute("DELETE FROM pending_batches WHERE expires_at < ?", (now,))

    def save(self, user_id: int, kind: str, payload: dict) -> str:
        self._purge_expired()
        pending_id = uuid.uuid4().hex[:12]
        expires = (datetime.now(timezone.utc) + timedelta(minutes=self.ttl_minutes)).isoformat()
        with self._connect() as conn:
            conn.execute(
                "INSERT INTO pending_batches (pending_id, telegram_user_id, kind, payload_json, expires_at) VALUES (?, ?, ?, ?, ?)",
                (pending_id, user_id, kind, json.dumps(payload), expires),
            )
        return pending_id

    def get(self, pending_id: str, user_id: int) -> dict | None:
        self._purge_expired()
        with self._connect() as conn:
            row = conn.execute(
                "SELECT kind, payload_json FROM pending_batches WHERE pending_id = ? AND telegram_user_id = ?",
                (pending_id, user_id),
            ).fetchone()
        if not row:
            return None
        return {"kind": row[0], "payload": json.loads(row[1])}

    def get_active_for_user(self, user_id: int) -> dict | None:
        self._purge_expired()
        with self._connect() as conn:
            row = conn.execute(
                "SELECT pending_id, kind, payload_json FROM pending_batches WHERE telegram_user_id = ? ORDER BY expires_at DESC LIMIT 1",
                (user_id,),
            ).fetchone()
        if not row:
            return None
        return {"pending_id": row[0], "kind": row[1], "payload": json.loads(row[2])}

    def delete(self, pending_id: str, user_id: int) -> None:
        with self._connect() as conn:
            conn.execute(
                "DELETE FROM pending_batches WHERE pending_id = ? AND telegram_user_id = ?",
                (pending_id, user_id),
            )

    def delete_for_user(self, user_id: int) -> None:
        with self._connect() as conn:
            conn.execute("DELETE FROM pending_batches WHERE telegram_user_id = ?", (user_id,))
