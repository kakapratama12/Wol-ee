"""Resolve batch items dengan exact match ke katalog (non-AI)."""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Any

from ai_parser import convert_to_base_quantity, format_amount


@dataclass
class ResolvedLine:
    raw_name: str
    quantity: float | None
    total: int | None
    quantity_unit: str | None
    status: str  # ok | not_found | missing_qty
    display_name: str | None = None
    product_id: int | None = None
    ingredient_id: int | None = None
    unit_price: float | None = None
    subtotal: float = 0.0
    base_unit: str | None = None


@dataclass
class BatchResolveResult:
    lines: list[ResolvedLine] = field(default_factory=list)
    partner_name: str | None = None
    partner_id: int | None = None
    partner_status: str | None = None  # ok | not_found | None

    @property
    def ok_lines(self) -> list[ResolvedLine]:
        return [line for line in self.lines if line.status == "ok"]

    @property
    def not_found_lines(self) -> list[ResolvedLine]:
        return [line for line in self.lines if line.status == "not_found"]

    @property
    def missing_qty_lines(self) -> list[ResolvedLine]:
        return [line for line in self.lines if line.status == "missing_qty"]

    @property
    def total(self) -> float:
        return sum(line.subtotal for line in self.ok_lines)


def exact_match_catalog(name: str, catalog: list[dict], key: str) -> dict | None:
    needle = name.strip().lower()
    for item in catalog:
        item_name = (item.get(key) or item.get("name") or "").strip().lower()
        if item_name == needle:
            return item
    return None


def resolve_partner(partner_name: str | None, partners: list[dict]) -> tuple[int | None, str | None]:
    if not partner_name:
        return None, None
    for partner in partners:
        if partner.get("name", "").strip().lower() == partner_name.strip().lower():
            return partner.get("id"), partner.get("name")
    return None, None


def resolve_sale_batch(items: list[dict], products: list[dict]) -> BatchResolveResult:
    result = BatchResolveResult()
    for item in items:
        name = (item.get("name") or "").strip()
        qty = item.get("quantity")
        total = item.get("total")
        line = ResolvedLine(
            raw_name=name,
            quantity=float(qty) if qty is not None else None,
            total=int(total) if total is not None else None,
            quantity_unit=item.get("quantity_unit"),
            status="missing_qty",
        )
        if not name:
            line.status = "not_found"
            result.lines.append(line)
            continue
        if qty is None or qty == 0:
            line.status = "missing_qty"
            result.lines.append(line)
            continue
        match = exact_match_catalog(name, products, "name")
        if not match:
            line.status = "not_found"
            result.lines.append(line)
            continue
        unit_price = float(total / qty) if total else float(match.get("selling_price", 0))
        line.status = "ok"
        line.display_name = match.get("name")
        line.product_id = match.get("id")
        line.unit_price = unit_price
        line.subtotal = unit_price * float(qty)
        result.lines.append(line)
    return result


def resolve_purchase_batch(
    items: list[dict],
    ingredients: list[dict],
    partner_name: str | None = None,
    partners: list[dict] | None = None,
) -> BatchResolveResult:
    result = BatchResolveResult(partner_name=partner_name)
    if partner_name:
        pid, pname = resolve_partner(partner_name, partners or [])
        if pid:
            result.partner_id = pid
            result.partner_name = pname
            result.partner_status = "ok"
        else:
            result.partner_status = "not_found"

    for item in items:
        name = (item.get("name") or "").strip()
        qty = item.get("quantity")
        total = item.get("total")
        unit = item.get("quantity_unit")
        line = ResolvedLine(
            raw_name=name,
            quantity=float(qty) if qty is not None else None,
            total=int(total) if total is not None else None,
            quantity_unit=unit,
            status="missing_qty",
        )
        if not name:
            line.status = "not_found"
            result.lines.append(line)
            continue
        if qty is None or qty == 0:
            line.status = "missing_qty"
            result.lines.append(line)
            continue
        if total is None:
            line.status = "missing_qty"
            result.lines.append(line)
            continue
        match = exact_match_catalog(name, ingredients, "ingredient")
        if not match:
            match = exact_match_catalog(name, ingredients, "name")
        if not match:
            line.status = "not_found"
            result.lines.append(line)
            continue
        base_unit = match.get("unit") or match.get("base_unit", "g")
        qty_base = convert_to_base_quantity(float(qty), unit, base_unit)
        unit_price = float(total) / max(qty_base, 1e-9)
        line.status = "ok"
        line.display_name = match.get("ingredient") or match.get("name")
        line.ingredient_id = match.get("id")
        line.base_unit = base_unit
        line.unit_price = unit_price
        line.subtotal = float(total)
        line.quantity = qty_base
        result.lines.append(line)
    return result


def parse_batch_regex(text: str) -> list[dict[str, Any]]:
    """Fallback ekstraksi item jika LLM gagal."""
    segments = re.split(r"[,;\n]+", text)
    items: list[dict[str, Any]] = []
    for segment in segments:
        segment = segment.strip()
        if not segment:
            continue
        label = re.match(r"^(.+?):\s*(.+)$", segment)
        if label:
            segment = label.group(2).strip()
        match = re.match(
            r"^(.+?)\s+(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|butir|pcs)?(?:\s+(?:rp\s*)?(\d+))?$",
            segment,
            re.IGNORECASE,
        )
        if not match:
            continue
        name, qty, unit, total = match.groups()
        item: dict[str, Any] = {
            "name": name.strip(),
            "quantity": float(qty.replace(",", ".")),
            "quantity_unit": unit,
        }
        if total:
            item["total"] = int(total)
        items.append(item)
    return items


def format_rupiah(amount: float) -> str:
    return f"Rp {amount:,.0f}".replace(",", ".")


def format_sale_preview(result: BatchResolveResult, title: str = "Laporan hari ini:") -> str:
    lines = [title, ""]
    for line in result.lines:
        if line.status == "ok" and line.display_name and line.quantity is not None:
            lines.append(
                f"✅ {line.display_name}: {int(line.quantity)} × {format_rupiah(line.unit_price or 0)} = {format_rupiah(line.subtotal)}"
            )
        elif line.status == "not_found":
            lines.append(f"❌ '{line.raw_name}' - produk tidak ditemukan")
        elif line.status == "missing_qty":
            lines.append(f"⚠️ '{line.raw_name}' - jumlah tidak jelas")
    if result.ok_lines:
        lines.extend(["", f"Total: {format_rupiah(result.total)}"])
    return "\n".join(lines)


def format_purchase_preview(result: BatchResolveResult) -> str:
    title = "Pembelian:"
    if result.partner_name and result.partner_status == "ok":
        title = f"Pembelian dari {result.partner_name}:"
    elif result.partner_name and result.partner_status == "not_found":
        title = f"Pembelian (partner '{result.partner_name}' tidak ditemukan):"
    lines = [title, ""]
    for line in result.lines:
        if line.status == "ok" and line.display_name and line.quantity is not None:
            unit = line.base_unit or ""
            lines.append(
                f"✅ {line.display_name}: {line.quantity:g}{unit} × {format_rupiah(line.unit_price or 0)} = {format_rupiah(line.subtotal)}"
            )
        elif line.status == "not_found":
            lines.append(f"❌ '{line.raw_name}' - bahan tidak ditemukan")
        elif line.status == "missing_qty":
            lines.append(f"⚠️ '{line.raw_name}' - data tidak lengkap")
    if result.ok_lines:
        lines.extend(["", f"Total: {format_rupiah(result.total)}", "Stok bertambah semua setelah dikonfirmasi."])
    return "\n".join(lines)
