"""Antrian offline untuk request API yang gagal (timeout)."""

from __future__ import annotations

import json
import sqlite3
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


class OfflineQueue:
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
                CREATE TABLE IF NOT EXISTS offline_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    action TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )
                """
            )

    def enqueue(self, action: str, payload: dict[str, Any]) -> None:
        with self._connect() as conn:
            conn.execute(
                "INSERT INTO offline_queue (action, payload, created_at) VALUES (?, ?, ?)",
                (action, json.dumps(payload), datetime.now(timezone.utc).isoformat()),
            )

    def pending(self) -> list[tuple[int, str, dict[str, Any]]]:
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT id, action, payload FROM offline_queue ORDER BY id ASC"
            ).fetchall()
        return [(row[0], row[1], json.loads(row[2])) for row in rows]

    def remove(self, queue_id: int) -> None:
        with self._connect() as conn:
            conn.execute("DELETE FROM offline_queue WHERE id = ?", (queue_id,))
