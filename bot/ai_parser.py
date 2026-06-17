"""AI Parser — NL parsing untuk Wol-ee inventory (referensi logic keuangan-bot)."""

from __future__ import annotations

import json
import re
import time
from datetime import datetime
from typing import Any

import httpx

from config import config

# Prompt akuntansi legacy (tetap untuk referensi / format_amount)
PARSE_PROMPT = """Kamu adalah asisten keuangan untuk UMKM Indonesia.
Tugas kamu: parse input user menjadi data transaksi.

ATURAN PENTING — AR (Piutang) vs Cash Sale:
- "Klien Budi beli barang Rp 5 juta" → AR (piutang), karena belum disebut bayar
- "Klien Budi beli barang Rp 5 juta sudah bayar" → Cash sale (income)

Output JSON:
{
    "type": "expense" atau "income",
    "amount": angka (dalam Rupiah, tanpa simbol),
    "category": kategori yang sesuai,
    "description": deskripsi singkat,
    "partner_name": nama partner (jika ada),
    "partner_type": "customer" atau "supplier" (jika ada),
    "is_receivable": true/false,
    "is_payable": true/false,
    "is_payment": true/false,
    "payment_amount": angka (jika pembayaran)
}

HANYA return JSON, tanpa penjelasan tambahan.
"""

WOLEE_INVENTORY_PROMPT = """Kamu adalah asisten inventory untuk kafe/bakery UMKM Indonesia (Wol-ee).
Parse input user menjadi transaksi inventory.

Katalog bahan baku tenant:
{ingredients}

Katalog produk jadi tenant:
{products}

Intent yang didukung:
- purchase: beli SATU bahan baku (contoh: "Beli tepung 2kg Rp 36 ribu")
- sale: jual SATU produk (contoh: "Jual matcha latte 10")
- sale_batch: laporan penjualan multi-item / copas dari WA (contoh: "laporan hari ini: matcha 10, croissant 5" atau multi-baris)
- purchase_batch: pembelian multi-bahan (contoh: "beli: tepung 10kg 100000, gula 5kg 50000" atau "beli dari CV Tepung: ...")
- stock: cek stok (contoh: "stok tepung")
- unknown: tidak bisa diparse

ATURAN PENTING:
- JANGAN map/menebak nama ke katalog. Kirim nama persis seperti user tulis.
- amount/total dalam Rupiah integer (200 ribu = 200000, 50rb = 50000)
- quantity_unit: kg, g, ml, l, butir, pcs, cup, atau null
- Untuk sale_batch/purchase_batch: ekstrak SEMUA baris item ke array items
- Pahami konteks laporan (mis. "nih dari barista", "laporan hari ini") → intent sale_batch
- partner_name: isi jika user menyebut supplier (mis. "beli dari CV Tepung")

Output JSON untuk single item:
{{
    "intent": "purchase" | "sale" | "stock" | "unknown",
    "ingredient": "nama bahan atau null",
    "product": "nama produk atau null",
    "quantity": angka atau null,
    "quantity_unit": "kg" | "g" | "ml" | "l" | "butir" | "pcs" | "cup" | null,
    "total": angka Rupiah untuk purchase atau null,
    "partner_name": "nama partner atau null",
    "note": "deskripsi singkat atau null"
}}

Output JSON untuk batch:
{{
    "intent": "sale_batch" | "purchase_batch",
    "partner_name": "nama partner atau null",
    "items": [
        {{"name": "nama item", "quantity": angka, "quantity_unit": "kg"|null, "total": angka|null}}
    ],
    "note": "deskripsi singkat atau null"
}}

HANYA return JSON, tanpa penjelasan tambahan.
"""

UNIT_TO_BASE: dict[str, dict[str, float]] = {
    "g": {"kg": 1000, "g": 1, "gram": 1},
    "ml": {"l": 1000, "ml": 1, "liter": 1000},
    "butir": {"butir": 1, "pcs": 1},
    "pcs": {"pcs": 1, "biji": 1, "cup": 1, "porsi": 1},
}

MONTH_NAMES: dict[str, int] = {
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


async def call_groq(user_input: str, system_prompt: str) -> dict:
    if not config.GROQ_API_KEY:
        return {"error": "Groq API key not configured", "provider": "groq", "model": config.GROQ_MODEL}

    started = time.perf_counter()
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{config.GROQ_BASE_URL}/chat/completions",
                headers={
                    "Authorization": f"Bearer {config.GROQ_API_KEY}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": config.GROQ_MODEL,
                    "messages": [
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_input},
                    ],
                    "temperature": 0.1,
                    "max_tokens": 800,
                },
                timeout=10.0,
            )
            latency_ms = int((time.perf_counter() - started) * 1000)
            if response.status_code == 200:
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                usage = payload.get("usage") or {}
                return {
                    "raw": content,
                    "provider": "groq",
                    "model": config.GROQ_MODEL,
                    "status": "success",
                    "latency_ms": latency_ms,
                    "usage": usage,
                }
            return {
                "error": f"Groq API error: {response.status_code}",
                "provider": "groq",
                "model": config.GROQ_MODEL,
                "status": "error",
                "error_code": f"http_{response.status_code}",
                "latency_ms": latency_ms,
            }
    except Exception as exc:
        latency_ms = int((time.perf_counter() - started) * 1000)
        return {
            "error": f"Groq error: {exc}",
            "provider": "groq",
            "model": config.GROQ_MODEL,
            "status": "error",
            "error_code": exc.__class__.__name__,
            "latency_ms": latency_ms,
        }


async def call_openrouter(user_input: str, system_prompt: str) -> dict:
    if not config.OPENROUTER_API_KEY:
        return {"error": "OpenRouter API key not configured", "provider": "openrouter", "model": config.OPENROUTER_MODEL}

    started = time.perf_counter()
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{config.OPENROUTER_BASE_URL}/chat/completions",
                headers={
                    "Authorization": f"Bearer {config.OPENROUTER_API_KEY}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": config.OPENROUTER_MODEL,
                    "messages": [
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_input},
                    ],
                    "temperature": 0.1,
                    "max_tokens": 800,
                },
                timeout=10.0,
            )
            latency_ms = int((time.perf_counter() - started) * 1000)
            if response.status_code == 200:
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                usage = payload.get("usage") or {}
                return {
                    "raw": content,
                    "provider": "openrouter",
                    "model": config.OPENROUTER_MODEL,
                    "status": "success",
                    "latency_ms": latency_ms,
                    "usage": usage,
                }
            return {
                "error": f"OpenRouter API error: {response.status_code}",
                "provider": "openrouter",
                "model": config.OPENROUTER_MODEL,
                "status": "error",
                "error_code": f"http_{response.status_code}",
                "latency_ms": latency_ms,
            }
    except Exception as exc:
        latency_ms = int((time.perf_counter() - started) * 1000)
        return {
            "error": f"OpenRouter error: {exc}",
            "provider": "openrouter",
            "model": config.OPENROUTER_MODEL,
            "status": "error",
            "error_code": exc.__class__.__name__,
            "latency_ms": latency_ms,
        }


async def _call_llm(user_input: str, system_prompt: str, is_pro: bool = False) -> dict:
    if is_pro:
        result = await call_openrouter(user_input, system_prompt)
    else:
        result = await call_groq(user_input, system_prompt)
        if "error" in result:
            result = await call_openrouter(user_input, system_prompt)
    return result


def _extract_json(content: str) -> dict | None:
    if not content or "{" not in content or "}" not in content:
        return None
    json_str = content[content.index("{") : content.rindex("}") + 1]
    try:
        return json.loads(json_str)
    except json.JSONDecodeError:
        return None


def format_amount(amount: float) -> str:
    sign = "-" if amount < 0 else ""
    value = abs(amount)
    if value >= 1_000_000:
        return f"{sign}Rp {value / 1_000_000:.1f}jt"
    if value >= 1_000:
        return f"{sign}Rp {value / 1_000:.0f}rb"
    return f"{sign}Rp {value:.0f}"


def _format_catalog_ingredients(items: list[dict]) -> str:
    if not items:
        return "(belum ada bahan)"
    lines = []
    for item in items[:30]:
        lines.append(f"- {item.get('ingredient', item.get('name', '?'))} ({item.get('unit', item.get('base_unit', ''))})")
    return "\n".join(lines)


def _format_catalog_products(items: list[dict]) -> str:
    if not items:
        return "(belum ada produk)"
    return "\n".join(f"- {p.get('name', '?')}" for p in items[:30])


def convert_to_base_quantity(quantity: float, unit: str | None, base_unit: str) -> float:
    if not unit or unit.lower() == base_unit.lower():
        return quantity
    base_unit = base_unit.lower()
    unit = unit.lower()
    converters = UNIT_TO_BASE.get(base_unit, {})
    if unit in converters:
        return quantity * converters[unit]
    if base_unit == "g" and unit == "kg":
        return quantity * 1000
    if base_unit == "ml" and unit in {"l", "liter"}:
        return quantity * 1000
    return quantity


def parse_regex_inventory(text: str) -> dict | None:
    purchase = re.search(
        r"beli\s+(.+?)\s+(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|butir|pcs)?\s+(?:rp\s*)?(\d+)",
        text,
        re.IGNORECASE,
    )
    if purchase:
        ingredient, qty, unit, total = purchase.groups()
        return {
            "intent": "purchase",
            "ingredient": ingredient.strip(),
            "quantity": float(qty.replace(",", ".")),
            "quantity_unit": unit,
            "total": int(total),
        }

    sale = re.search(r"jual\s+(.+?)\s+(\d+)\s*$", text, re.IGNORECASE)
    if sale:
        product, qty = sale.groups()
        return {"intent": "sale", "product": product.strip(), "quantity": int(qty)}

    stock = re.search(r"stok\s+(.+)", text, re.IGNORECASE)
    if stock or text.strip().lower() in {"stok", "cek stok"}:
        return {
            "intent": "stock",
            "ingredient": stock.group(1).strip() if stock else None,
        }

    return None


def _parse_rupiah_amount(text: str) -> tuple[int, str] | None:
    match = re.search(
        r"(?:rp\s*)?(\d+(?:[.,]\d+)?)\s*(jt|juta|rb|ribu|k)?",
        text,
        re.IGNORECASE,
    )
    if not match:
        return None

    raw_amount, suffix = match.groups()
    amount = float(raw_amount.replace(",", "."))
    suffix = (suffix or "").lower()
    if suffix in {"jt", "juta"}:
        amount *= 1_000_000
    elif suffix in {"rb", "ribu", "k"}:
        amount *= 1_000

    return int(amount), match.group(0)


def _parse_expense_period(text: str) -> tuple[int, int]:
    normalized = text.lower()
    now = datetime.now()
    for name, month in MONTH_NAMES.items():
        if name in normalized:
            year_match = re.search(r"(20\d{2})", normalized)
            return month, int(year_match.group(1)) if year_match else now.year
    if "bulan lalu" in normalized:
        if now.month == 1:
            return 12, now.year - 1
        return now.month - 1, now.year
    return now.month, now.year


def parse_regex_expense(text: str) -> dict | None:
    normalized = text.strip().lower()
    if not re.search(r"\b(bayar|biaya|expense|pengeluaran)\b", normalized):
        return None

    parsed_amount = _parse_rupiah_amount(normalized)
    if not parsed_amount:
        return None

    amount, amount_text = parsed_amount
    category = normalized
    category = re.sub(r"\b(bayar|biaya|expense|pengeluaran|untuk)\b", " ", category)
    category = category.replace(amount_text.lower(), " ")
    category = re.sub(r"\bbulan\s+(ini|lalu)\b", " ", category)
    category = re.sub(r"\b(20\d{2})\b", " ", category)
    for month_name in MONTH_NAMES:
        category = re.sub(rf"\b{month_name}\b", " ", category)
    category = re.sub(r"\s+", " ", category).strip(" ,-")

    if not category:
        category = "operasional"
    if "gaji" in category:
        category = "gaji"
    elif "listrik" in category:
        category = "listrik"
    elif "sewa" in category:
        category = "sewa"

    month, year = _parse_expense_period(normalized)
    return {
        "intent": "expense",
        "category": category.title(),
        "description": text.strip(),
        "amount": amount,
        "period_month": month,
        "period_year": year,
        "_provider": "regex",
    }


async def parse_wolee_inventory(
    user_input: str,
    ingredients: list[dict],
    products: list[dict],
    is_pro: bool = False,
) -> dict:
    regex_result = parse_regex_inventory(user_input)
    if regex_result:
        regex_result["_provider"] = "regex"
        return regex_result

    prompt = WOLEE_INVENTORY_PROMPT.format(
        ingredients=_format_catalog_ingredients(ingredients),
        products=_format_catalog_products(products),
    )
    result = await _call_llm(user_input, prompt, is_pro=is_pro)

    if "error" in result:
        return result

    parsed = _extract_json(result.get("raw", ""))
    if not parsed:
        return {
            "error": "Gak bisa parse input. Coba: \"Beli tepung 2kg Rp 36 ribu\" atau \"Jual matcha latte 10\"",
            "provider": result.get("provider", "unknown"),
            "model": result.get("model"),
            "status": "error",
            "error_code": "parse_failed",
            "latency_ms": result.get("latency_ms"),
            "usage": result.get("usage"),
        }

    parsed["_provider"] = result.get("provider", "unknown")
    parsed["_ai_request"] = {
        "provider": result.get("provider", "unknown"),
        "model": result.get("model"),
        "status": result.get("status", "success"),
        "error_code": result.get("error_code"),
        "latency_ms": result.get("latency_ms"),
        "usage": result.get("usage") or {},
    }
    intent = parsed.get("intent", "unknown")

    if intent in {"sale_batch", "purchase_batch"}:
        items = parsed.get("items") or []
        if not items:
            from batch_resolver import parse_batch_regex
            fallback_items = parse_batch_regex(user_input)
            if fallback_items:
                parsed["items"] = fallback_items
                parsed["_provider"] = parsed.get("_provider", "unknown") + "+regex"
            else:
                return {"error": "Tidak ada item yang terbaca. Contoh: matcha 10, croissant 5"}
        return parsed

    if intent == "purchase" and not parsed.get("total"):
        return {"error": "Nominal belum ketemu. Contoh: \"Beli tepung Rp 200 ribu\""}
    if intent == "sale" and not parsed.get("quantity"):
        return {"error": "Jumlah penjualan belum ketemu. Contoh: \"Jual matcha latte 10\""}

    return parsed


def to_api_purchase_payload(parsed: dict, stock_catalog: list[dict]) -> dict | None:
    ingredient_name = (parsed.get("ingredient") or "").strip()
    total = parsed.get("total")
    quantity = parsed.get("quantity")
    unit = parsed.get("quantity_unit")

    if not ingredient_name or not total:
        return None

    base_unit = "g"
    for item in stock_catalog:
        name = item.get("ingredient") or item.get("name", "")
        if name.lower() == ingredient_name.lower():
            base_unit = item.get("unit") or item.get("base_unit", "g")
            break

    if quantity is None or quantity == 0:
        quantity = 1.0

    qty_base = convert_to_base_quantity(float(quantity), unit, base_unit)

    payload: dict[str, Any] = {
        "ingredient": ingredient_name,
        "quantity": qty_base,
        "total": int(total),
    }
    if parsed.get("note"):
        payload["note"] = parsed["note"]
    return payload


def to_api_sale_payload(parsed: dict) -> dict | None:
    product = (parsed.get("product") or "").strip()
    quantity = parsed.get("quantity")
    if not product or not quantity:
        return None
    payload: dict[str, Any] = {"product": product, "quantity": int(quantity)}
    if parsed.get("note"):
        payload["note"] = parsed["note"]
    return payload


def to_api_expense_payload(parsed: dict) -> dict | None:
    if parsed.get("intent") != "expense":
        return None
    amount = parsed.get("amount")
    category = parsed.get("category")
    if not amount or not category:
        return None
    return {
        "category": category,
        "description": parsed.get("description"),
        "amount": int(amount),
        "period_month": int(parsed.get("period_month")),
        "period_year": int(parsed.get("period_year")),
    }


async def parse_transaction(user_input: str, is_pro: bool = False) -> dict:
    """Legacy accounting parser — untuk user non-Wol-ee (local DB path)."""
    result = await _call_llm(user_input, PARSE_PROMPT, is_pro=is_pro)

    if "error" in result:
        return result

    content = result.get("raw", "")
    if content is None:
        return {"error": 'AI gak bisa parse input ini. Coba tambahkan nominal, contoh: "Beli bahan Rp 200 ribu"'}

    parsed = _extract_json(content.strip())
    if not parsed:
        return {"error": "Format output AI gak valid. Coba lagi."}

    parsed["_provider"] = result.get("provider", "unknown")
    amount = parsed.get("amount")
    if amount is None or amount == 0:
        return {"error": 'Nominal gak ketemu. Tulis dengan nominal, contoh: "Beli bahan Rp 200 ribu"'}

    return parsed


def format_transaction(result: dict) -> str:
    """Format parsed legacy transaction for user confirmation."""
    if "error" in result:
        return f"❌ {result['error']}"

    if result.get("is_payment"):
        return f"""💳 <b>Pembayaran Dicatat!</b>

👤 Partner: {result.get('partner_name', 'Unknown')}
💰 Pembayaran: <b>{format_amount(result.get('payment_amount', 0))}</b>
📝 {result.get('description', '')}

Konfirmasi?"""

    if result.get("is_receivable"):
        return f"""📋 <b>Piutang (AR)</b>

👤 Klien: <b>{result.get('partner_name', 'Unknown')}</b>
💰 Hutang: <b>{format_amount(result['amount'])}</b>
📝 {result.get('description', '')}

Konfirmasi?"""

    if result.get("is_payable"):
        return f"""📋 <b>Hutang (AP)</b>

👤 Supplier: <b>{result.get('partner_name', 'Unknown')}</b>
💰 Hutang: <b>{format_amount(result['amount'])}</b>
📝 {result.get('description', '')}

Konfirmasi?"""

    type_emoji = "📈" if result["type"] == "income" else "📉"
    type_text = "Pemasukan" if result["type"] == "income" else "Pengeluaran"
    partner_text = ""
    if result.get("partner_name"):
        partner_text = f"\n👤 Partner: {result['partner_name']}"

    return f"""{type_emoji} <b>{type_text}</b>

💰 Jumlah: <b>{format_amount(result['amount'])}</b>
📂 Kategori: {result['category']}
📝 Deskripsi: {result['description']}{partner_text}

Konfirmasi?"""
