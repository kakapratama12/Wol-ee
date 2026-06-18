"""AI Interpreter — Natural language interpretation for Wol-ee reports.

Takes raw data from API endpoints and generates human-readable
insights using LLM. This is what makes the bot feel "smart."

Tier behavior:
    - Free: template responses (no AI cost)
    - Pro/Business: AI interpretation via LLM
"""

import json
import logging
from typing import Any

from ai_parser import call_groq, call_openrouter

logger = logging.getLogger(__name__)

# ─── Prompts ────────────────────────────────────────────────────────────────

REPORT_INTERPRETATION_PROMPT = """Kamu adalah Wol-ee, asisten keuangan UMKM yang cerdas dan friendly.
Kamu membantu owner memahami kondisi bisnisnya.

Berdasarkan data laporan berikut, buat rangkuman yang:
1. Singkat (3-5 kalimat)
2. Highlight angka penting (omset, profit, margin)
3. Bandingkan dengan periode sebelumnya JIKA ada datanya
4. Kasih 1-2 insight actionable (bukan saran umum)
5. Tone: casual tapi profesional, pakai "kamu"

Data laporan:
{data}

Pertanyaan user: {question}

Jawab dalam Bahasa Indonesia. Jangan pakai emoji. Jangan pakai bullet points — cukup paragraf natural."""

CONTEXT_AWARE_PROMPT = """Kamu adalah Wol-ee, asisten keuangan UMKM yang cerdas.
Kamu ingat percakapan sebelumnya dengan user ini.

Riwayat percakapan:
{context}

Data terkini:
{data}

Pertanyaan user: {question}

Buat jawaban yang:
1. Nyambung dengan percakapan sebelumnya (jika relevan)
2. Referensikan data yang sudah disebut sebelumnya
3. Singkat, actionable, 3-5 kalimat
4. Tone: casual tapi profesional

Jawab dalam Bahasa Indonesia. Jangan pakai emoji."""


# ─── Main Functions ─────────────────────────────────────────────────────────

async def interpret_report(
    data: dict[str, Any],
    question: str = "",
    context: list[dict] | None = None,
    is_pro: bool = False,
) -> str:
    """Interpret report data using LLM.

    Args:
        data: Raw API response (today's report, PnL, etc.)
        question: User's original question
        context: Previous conversation history
        is_pro: Whether to use premium LLM

    Returns:
        Natural language interpretation of the data
    """
    # Format data for prompt
    data_str = json.dumps(data, indent=2, ensure_ascii=False, default=str)

    # Choose prompt based on context
    if context and len(context) > 2:
        prompt = CONTEXT_AWARE_PROMPT.format(
            context=_format_context(context),
            data=data_str,
            question=question or "Jelaskan kondisi bisnis saya",
        )
    else:
        prompt = REPORT_INTERPRETATION_PROMPT.format(
            data=data_str,
            question=question or "Jelaskan kondisi bisnis saya",
        )

    try:
        if is_pro:
            result = await call_openrouter(prompt, temperature=0.3, max_tokens=500)
        else:
            result = await call_groq(prompt, temperature=0.3, max_tokens=500)

        if result and result.get("text"):
            return result["text"].strip()
        else:
            return _fallback_summary(data)
    except Exception as e:
        logger.error(f"AI interpretation failed: {e}")
        return _fallback_summary(data)


def interpret_report_sync(
    data: dict[str, Any],
    question: str = "",
    context: list[dict] | None = None,
    is_pro: bool = False,
) -> str | None:
    """Sync wrapper for interpret_report — use from sync handlers."""
    import asyncio
    try:
        loop = asyncio.new_event_loop()
        try:
            return loop.run_until_complete(
                interpret_report(data, question, context, is_pro)
            )
        finally:
            loop.close()
    except Exception as e:
        logger.error(f"Sync interpretation failed: {e}")
        return None


def _format_context(context: list[dict]) -> str:
    """Format conversation history for prompt injection."""
    lines = []
    for msg in context[-6:]:  # Last 3 exchanges
        role = "User" if msg["role"] == "user" else "Wol-ee"
        lines.append(f"{role}: {msg['message']}")
    return "\n".join(lines)


def _fallback_summary(data: dict) -> str:
    """Generate a basic summary when AI fails."""
    parts = []

    # Today's report
    if "total_revenue" in data:
        rev = data.get("total_revenue", 0)
        cogs = data.get("total_cogs", 0)
        profit = rev - cogs
        margin = (profit / rev * 100) if rev > 0 else 0
        parts.append(f"Omset hari ini Rp {rev:,.0f}")
        if cogs > 0:
            parts.append(f"COGS Rp {cogs:,.0f}")
        parts.append(f"Profit Rp {profit:,.0f} (margin {margin:.1f}%)")

    # PnL
    elif "revenue" in data:
        rev = data.get("revenue", 0)
        cogs = data.get("cogs", 0)
        expenses = data.get("expenses", 0)
        profit = rev - cogs - expenses
        parts.append(f"Revenue bulan ini Rp {rev:,.0f}")
        parts.append(f"Profit bersih Rp {profit:,.0f}")

    # Stock alerts
    elif "alerts" in data:
        alerts = data.get("alerts", [])
        if alerts:
            parts.append(f"Ada {len(alerts)} item yang perlu diperhatikan:")
            for a in alerts[:3]:
                parts.append(f"- {a.get('name', '?')}: {a.get('status', '?')}")
        else:
            parts.append("Semua stok dalam kondisi aman.")

    return "\n\n".join(parts) if parts else "Data belum tersedia."
