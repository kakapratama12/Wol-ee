"""Structured Telegram reply with optional inline keyboard."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass
class BotResponse:
    text: str
    reply_markup: dict | None = None
    parse_mode: str = "HTML"
