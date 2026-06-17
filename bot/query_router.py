"""Query router — klasifikasi pertanyaan laporan/pantau tanpa LLM."""

from __future__ import annotations

import re
from datetime import datetime

MONTH_MAP: dict[str, int] = {
    "januari": 1,
    "februari": 2,
    "maret": 3,
    "april": 4,
    "mei": 5,
    "juni": 6,
    "juli": 7,
    "agustus": 8,
    "september": 9,
    "oktober": 10,
    "november": 11,
    "desember": 12,
}


def _looks_like_transaction(text: str) -> bool:
    normalized = text.strip().lower()
    if normalized.startswith(("beli ", "jual ")):
        return True
    if re.search(r"\d+\s*,\s*\w+", normalized):
        return True
    if re.search(r"\b(matcha|latte|croissant|kopi|tepung|gula|susu)\b.*\d+", normalized):
        if "profit" not in normalized and "omset" not in normalized and "laporan" not in normalized:
            return True
    return False


def parse_period(text: str) -> tuple[int, int]:
    """Return (month, year) for PnL queries. Defaults to current month."""
    normalized = text.strip().lower()
    now = datetime.now()

    for name, month in MONTH_MAP.items():
        if name in normalized:
            year_match = re.search(r"(20\d{2})", normalized)
            year = int(year_match.group(1)) if year_match else now.year
            return month, year

    if "bulan lalu" in normalized:
        if now.month == 1:
            return 12, now.year - 1
        return now.month - 1, now.year

    return now.month, now.year


def classify_query(text: str) -> str | None:
    """
    Return query kind or None if not a read-only query.
    Kinds: capabilities, stock_alerts, margin_alerts, top_products, report_today, report_pnl
    """
    normalized = text.strip().lower().lstrip("/")
    if not normalized or _looks_like_transaction(text):
        return None

    capability_hints = (
        "bisa nanya",
        "bisa apa",
        "kamu bisa",
        "bisa ngapain",
        "fitur apa",
        "help wolee",
        "bantuan wolee",
    )
    if normalized in {"bantuan", "help", "help wolee"} or any(h in normalized for h in capability_hints):
        return "capabilities"

    stock_hints = (
        "stok menipis",
        "stok kritis",
        "stok bermasalah",
        "bahan menipis",
        "bahan kritis",
        "ada yang menipis",
        "ada stok kritis",
        "stok aman",
    )
    if any(h in normalized for h in stock_hints):
        return "stock_alerts"

    margin_hints = (
        "margin turun",
        "margin jeblok",
        "produk boncos",
        "margin jelek",
        "produk rugi",
    )
    if any(h in normalized for h in margin_hints):
        return "margin_alerts"

    top_product_hints = (
        "paling laku",
        "barang laku",
        "produk laku",
        "best seller",
        "bestseller",
        "terlaris",
        "paling banyak dijual",
    )
    if any(h in normalized for h in top_product_hints):
        return "top_products"

    today_hints = (
        "profit hari ini",
        "omset hari ini",
        "untung hari ini",
        "laporan hari ini",
        "hari ini gimana",
        "hari ini bagaimana",
        "penjualan hari ini",
    )
    if normalized in {"profit", "ringkasan hari ini"} or any(h in normalized for h in today_hints):
        return "report_today"

    pnl_hints = (
        "profit bulan",
        "omset bulan",
        "laba bulan",
        "laporan bulan",
        "ringkasan bulan",
        "laporan profit",
        "profit bulan ini",
        "omset bulan ini",
        "bulan ini",
        "ringkasan",
        "summary",
        "pnl",
        "laba bersih",
        "kenapa rugi",
        "kok bisa rugi",
        "knp rugi",
        "knp kok bisa rugi",
        "kenapa bisa rugi",
    )
    if any(h in normalized for h in pnl_hints) or any(m in normalized for m in MONTH_MAP):
        if "hari ini" not in normalized:
            return "report_pnl"

    return None
