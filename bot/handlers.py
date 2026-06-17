"""Handler pesan bot — integrasi Wol-ee API dengan NL parsing."""

from __future__ import annotations

import asyncio
import logging
import re
from typing import Callable

from ai_parser import (
    format_amount,
    parse_money_amount,
    parse_period_text,
    parse_quantity_unit,
    parse_wolee_inventory,
    to_api_expense_payload,
    to_api_purchase_payload,
    to_api_sale_payload,
)
from batch_resolver import (
    BatchResolveResult,
    format_purchase_preview,
    format_sale_preview,
    resolve_partner,
    resolve_purchase_batch,
    resolve_sale_batch,
)
from bot_response import BotResponse
from bot_storage import BotUserStorage
from not_found import format_item_not_found, get_app_url
from offline_queue import OfflineQueue
from pending_batch import PendingBatchStorage
from query_router import classify_query, parse_period
from wol_ee_client import WolEeApiError, WolEeClient

logger = logging.getLogger(__name__)


class BotHandlers:
    def __init__(
        self,
        api_url: str,
        default_token: str,
        storage: BotUserStorage,
        queue: OfflineQueue,
        pending: PendingBatchStorage,
        client_factory: Callable[[str], WolEeClient] | None = None,
    ):
        self.api_url = api_url
        self.default_token = default_token
        self.storage = storage
        self.queue = queue
        self.pending = pending
        self.client_factory = client_factory or (lambda token: WolEeClient(api_url, token))

    def handle_start(self, user_id: int, text: str) -> str:
        parts = text.strip().split(maxsplit=1)
        if len(parts) < 2:
            return "Masukkan token dari dashboard Wol-ee.\nContoh: /start 1:abc123def456"

        token = parts[1].strip()
        try:
            payload = WolEeClient.validate_token(self.api_url, token)
            tenant = payload["data"]["tenant"]
            self.storage.register(
                user_id,
                tenant["id"],
                token,
                tenant_plan=tenant.get("plan", "free"),
            )
            return (
                f"Berhasil terdaftar ke {tenant['name']}!\n\n"
                "Kamu bisa ketik bahasa natural, contoh:\n"
                "• Beli tepung Rp 200 ribu\n"
                "• Jual matcha latte 10\n"
                "• profit bulan ini — laporan keuangan\n"
                "• stok menipis — pantau bahan\n"
                "• Ketik \"bisa nanya apa\" untuk lihat semua fitur"
            )
        except WolEeApiError:
            return "Token tidak valid. Minta Owner generate token di dashboard > Bot Integration."

    def _client_for(self, user_id: int) -> WolEeClient | None:
        token = self.storage.get_token(user_id) or self.default_token
        if not token:
            return None
        return self.client_factory(token)

    def _catalog(self, client: WolEeClient) -> tuple[list[dict], list[dict]]:
        ingredients = client.get_stock().get("data", [])
        products = client.list_products().get("data", [])
        return ingredients, products

    def handle_pending_reply(self, user_id: int, text: str) -> str | BotResponse | None:
        active = self.pending.get_active_for_user(user_id)
        if not active:
            return None

        if active["kind"] == "awaiting_slot":
            return self._handle_pending_slot_reply(user_id, text, active)

        if active["kind"] != "awaiting_qty":
            return None

        qty_match = re.search(r"(\d+)", text.strip())
        if not qty_match:
            return "⚠️ Ketik angka jumlah, contoh: 10"

        payload = active["payload"]
        item_index = payload.get("item_index", 0)
        items = payload.get("items", [])
        if item_index >= len(items):
            self.pending.delete(active["pending_id"], user_id)
            return "❌ Sesi kedaluwarsa. Kirim ulang laporan."

        items[item_index]["quantity"] = int(qty_match.group(1))
        payload["items"] = items
        self.pending.delete(active["pending_id"], user_id)

        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            ingredients, products = self._catalog(client)
        except WolEeApiError:
            return "❌ Gagal mengambil katalog dari API."

        intent = payload.get("intent", "sale_batch")
        if intent == "purchase_batch":
            partners = client.list_partners()
            result = resolve_purchase_batch(
                items,
                ingredients,
                partner_name=payload.get("partner_name"),
                partners=partners,
            )
            return self._build_batch_preview(user_id, "purchase_batch", result, payload.get("note"), partners, ingredients, products)

        result = resolve_sale_batch(items, products)
        return self._build_batch_preview(user_id, "sale_batch", result, payload.get("note"), None, ingredients, products)

    def _handle_pending_slot_reply(self, user_id: int, text: str, active: dict) -> str | BotResponse:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        payload = active["payload"]
        parsed = payload.get("parsed", {})
        slot = payload.get("missing_slot")
        value_error = self._merge_slot_answer(parsed, slot, text)
        if value_error:
            return value_error

        try:
            ingredients, products = self._catalog(client)
        except WolEeApiError:
            return "❌ Gagal mengambil katalog dari API."

        reply = self._execute_action_plan(user_id, client, parsed, ingredients, products)
        if not self._needs_clarification(reply):
            self.pending.delete(active["pending_id"], user_id)
        return reply

    def _merge_slot_answer(self, parsed: dict, slot: str | None, text: str) -> str | None:
        if slot == "amount":
            amount = parse_money_amount(text)
            if not amount:
                return "Nominalnya belum kebaca. Contoh: 60rb atau Rp 60.000"
            parsed["amount"] = amount
            parsed["total"] = amount
            return None

        if slot in {"quantity", "quantity_unit"}:
            quantity, unit = parse_quantity_unit(text)
            if quantity is None:
                return "Jumlahnya belum kebaca. Contoh: 2kg atau 10 pcs"
            parsed["quantity"] = quantity
            if unit:
                parsed["quantity_unit"] = unit
            return None

        if slot in {"product", "ingredient", "category"}:
            value = text.strip()
            if len(value) < 2:
                return "Jawabannya belum jelas. Coba tulis lebih spesifik."
            parsed[slot] = value
            return None

        if slot in {"period_month", "period_year"}:
            period = parse_period_text(text)
            if not period:
                return "Periodenya belum kebaca. Contoh: bulan ini atau Juni 2026."
            parsed["period_month"], parsed["period_year"] = period
            return None

        return "Aku belum bisa melengkapi data itu. Kirim ulang perintah lengkap ya."

    def handle_query(self, user_id: int, text: str, kind: str) -> str:
        if kind == "capabilities":
            return self.handle_capabilities(user_id)
        if kind == "stock_alerts":
            return self.handle_stock_alerts(user_id)
        if kind == "margin_alerts":
            return self.handle_margin_alerts(user_id)
        if kind == "top_products":
            month, year = parse_period(text)
            return self.handle_top_products(user_id, month, year)
        if kind == "bottom_products":
            month, year = parse_period(text)
            return self.handle_bottom_products(user_id, month, year)
        if kind == "business_insight":
            month, year = parse_period(text)
            return self.handle_business_insight(user_id, month, year)
        if kind == "report_today":
            return self.handle_report_today(user_id)
        if kind == "report_pnl":
            month, year = parse_period(text)
            return self.handle_report_pnl(user_id, month, year)
        return self.handle_capabilities(user_id)

    def _ensure_ai_quota(self, client: WolEeClient, user_id: int) -> str | None:
        try:
            client.consume_ai_quota(user_id)
            return None
        except WolEeApiError as exc:
            if exc.error_code == "AI_QUOTA_EXCEEDED":
                self._log_ai_request(client, user_id, {"status": "quota_exceeded"})
                return (
                    "⚠️ Kuota AI hari ini habis.\n"
                    "Reset besok jam 00:00 WIB.\n"
                    "Upgrade ke Pro untuk kuota lebih besar & respons lebih akurat."
                )
            logger.warning("Gagal cek kuota AI: %s", exc)
            return None

    def _log_ai_request(self, client: WolEeClient, user_id: int, meta: dict) -> None:
        plan = self.storage.get_tenant_plan(user_id)
        usage = meta.get("usage") or {}
        provider = meta.get("provider") or ("openrouter" if plan in {"pro", "business"} else "groq")
        try:
            client.post_ai_request(
                {
                    "telegram_user_id": user_id,
                    "plan": plan,
                    "provider": provider,
                    "model": meta.get("model"),
                    "status": meta.get("status", "success"),
                    "error_code": meta.get("error_code"),
                    "latency_ms": meta.get("latency_ms"),
                    "prompt_tokens": usage.get("prompt_tokens"),
                    "completion_tokens": usage.get("completion_tokens"),
                    "total_tokens": usage.get("total_tokens"),
                }
            )
        except WolEeApiError as exc:
            logger.warning("Gagal mencatat AI request: %s", exc)

    def handle_feedback(self, user_id: int, text: str, original_message: str | None = None) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        feedback_text = re.sub(r"^/?feedback\\b", "", text.strip(), flags=re.IGNORECASE).strip()
        if len(feedback_text) < 3:
            return (
                "Tulis feedback setelah kata <b>feedback</b>.\n"
                "Contoh: feedback bandingin profit bulan ini vs bulan lalu"
            )

        try:
            client.post_feedback(user_id, feedback_text, original_message=original_message)
            self.storage.touch(user_id)
            return (
                "✅ Feedback dicatat. Tim Wol-ee akan review dulu apakah relevan "
                "untuk fitur bisnis F&B."
            )
        except WolEeApiError:
            return "❌ Gagal mencatat feedback. Coba lagi nanti."

    async def handle_natural_language(self, user_id: int, text: str, is_pro: bool = False) -> str | BotResponse:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        quota_msg = self._ensure_ai_quota(client, user_id)
        if quota_msg:
            return quota_msg

        is_pro = self.storage.uses_premium_llm(user_id)

        try:
            ingredients, products = await asyncio.to_thread(self._catalog, client)
        except WolEeApiError:
            return "❌ Gagal mengambil katalog dari API."

        parsed = await parse_wolee_inventory(text, ingredients, products, is_pro=is_pro)
        ai_request = parsed.get("_ai_request") or (
            parsed if parsed.get("provider") and parsed.get("provider") != "regex" else None
        )
        if ai_request:
            await asyncio.to_thread(self._log_ai_request, client, user_id, ai_request)
        if "error" in parsed:
            return f"❌ {parsed['error']}"

        return self._execute_action_plan(user_id, client, parsed, ingredients, products, original_text=text)

    def _execute_action_plan(
        self,
        user_id: int,
        client: WolEeClient,
        parsed: dict,
        ingredients: list[dict],
        products: list[dict],
        original_text: str | None = None,
    ) -> str | BotResponse:
        self._apply_deterministic_slot_hints(parsed, original_text)
        intent = parsed.get("intent", "unknown")

        if intent == "stock":
            return self.handle_stok(user_id, f"stok {parsed.get('ingredient') or ''}".strip())
        if intent == "purchase_batch":
            partners = client.list_partners()
            partner_name = parsed.get("partner_name")
            if partner_name:
                pid, _ = resolve_partner(partner_name, partners)
                if not pid:
                    names = [p.get("name", "") for p in partners]
                    return format_item_not_found("partner", partner_name, names, f"{get_app_url()}/partners")
            result = resolve_purchase_batch(
                parsed.get("items", []),
                ingredients,
                partner_name=partner_name,
                partners=partners,
            )
            return self._build_batch_preview(user_id, "purchase_batch", result, parsed.get("note"), partners, ingredients, products)
        if intent == "sale_batch":
            result = resolve_sale_batch(parsed.get("items", []), products)
            return self._build_batch_preview(user_id, "sale_batch", result, parsed.get("note"), None, ingredients, products)
        if intent == "purchase":
            clarification = self._clarification_for_action(user_id, parsed, original_text)
            if clarification:
                return clarification
            payload = to_api_purchase_payload(parsed, ingredients)
            if not payload:
                return "❌ Data pembelian tidak lengkap. Sebutkan bahan dan nominal."
            return self._build_action_preview(user_id, "purchase", payload, ingredients=ingredients)
        if intent == "sale":
            clarification = self._clarification_for_action(user_id, parsed, original_text)
            if clarification:
                return clarification
            payload = to_api_sale_payload(parsed)
            if not payload:
                return "❌ Data penjualan tidak lengkap. Sebutkan produk dan jumlah."
            return self._build_action_preview(user_id, "sale", payload, products=products)
        if intent == "expense":
            clarification = self._clarification_for_action(user_id, parsed, original_text)
            if clarification:
                return clarification
            payload = to_api_expense_payload(parsed)
            if not payload:
                return "❌ Data biaya tidak lengkap. Contoh: bayar listrik bulan ini 1.5jt"
            return self._build_action_preview(user_id, "expense", payload)

        return (
            "❌ Aku belum bisa memahami perintah itu.\n\n"
            "Yang bisa kamu coba:\n"
            "• profit bulan ini\n"
            "• barang paling laku\n"
            "• stok menipis\n"
            "• jual matcha 10\n"
            "• beli tepung 2kg Rp 36 ribu\n\n"
            "Kalau ini kebutuhan bisnis kamu, balas:\n"
            "feedback <perintah/pertanyaan yang kamu harapkan>\n"
            "Contoh: feedback bandingin profit bulan ini vs bulan lalu"
        )

    def _apply_deterministic_slot_hints(self, parsed: dict, original_text: str | None) -> None:
        if not original_text:
            return

        if parsed.get("intent") in {"sale", "purchase", "expense"} and not (parsed.get("amount") or parsed.get("total")):
            amount = parse_money_amount(original_text)
            if amount:
                parsed["amount"] = amount
                if parsed.get("intent") in {"sale", "purchase"}:
                    parsed["total"] = amount

        if parsed.get("intent") == "expense" and (not parsed.get("period_month") or not parsed.get("period_year")):
            period = parse_period_text(original_text)
            if period:
                parsed["period_month"], parsed["period_year"] = period

        if parsed.get("intent") == "purchase" and (not parsed.get("quantity") or not parsed.get("quantity_unit")):
            quantity, unit = parse_quantity_unit(original_text)
            if quantity is not None and not parsed.get("quantity"):
                parsed["quantity"] = quantity
            if unit and not parsed.get("quantity_unit"):
                parsed["quantity_unit"] = unit

    def _clarification_for_action(self, user_id: int, parsed: dict, original_text: str | None = None) -> str | None:
        missing = self._missing_required_slot(parsed)
        if not missing:
            return None

        self.pending.delete_for_user(user_id)
        self.pending.save(user_id, "awaiting_slot", {
            "parsed": parsed,
            "missing_slot": missing,
            "original_text": original_text,
        })
        return self._clarification_question(parsed, missing)

    def _missing_required_slot(self, parsed: dict) -> str | None:
        intent = parsed.get("intent")
        required = {
            "sale": ["product", "quantity"],
            "purchase": ["ingredient", "quantity", "quantity_unit", "total"],
            "expense": ["category", "amount", "period_month", "period_year"],
        }.get(intent, [])
        aliases = {"total": "amount"}
        for slot in required:
            value = parsed.get(slot)
            if slot == "total" and not value:
                value = parsed.get("amount")
            if value in {None, "", 0}:
                return aliases.get(slot, slot)
        return None

    def _clarification_question(self, parsed: dict, slot: str) -> str:
        intent = parsed.get("intent")
        if slot == "amount":
            if intent == "purchase":
                ingredient = parsed.get("ingredient") or "bahan itu"
                qty = parsed.get("quantity")
                unit = parsed.get("quantity_unit") or ""
                partner = parsed.get("partner_name")
                partner_text = f" dari {partner}" if partner else ""
                qty_text = f" {qty:g}{unit}" if isinstance(qty, (int, float)) else ""
                return f"Total beli {ingredient}{qty_text}{partner_text} berapa rupiah?"
            return "Nominal biayanya berapa rupiah? Contoh: 1.5jt atau Rp 1500000"
        if slot == "quantity":
            item = parsed.get("product") or parsed.get("ingredient") or "item itu"
            return f"Jumlah {item} berapa?"
        if slot == "quantity_unit":
            item = parsed.get("ingredient") or "bahan itu"
            return f"Satuan {item} apa? Contoh: kg, g, ml, butir, pcs."
        if slot == "product":
            return "Produk apa yang terjual?"
        if slot == "ingredient":
            return "Bahan apa yang dibeli?"
        if slot == "category":
            return "Biaya ini kategorinya apa? Contoh: Listrik, Gaji, Sewa."
        if slot in {"period_month", "period_year"}:
            return "Biaya ini untuk bulan apa? Contoh: bulan ini atau Juni 2026."
        return "Ada data yang kurang. Bisa lengkapi detailnya?"

    def _needs_clarification(self, reply: str | BotResponse) -> bool:
        if isinstance(reply, BotResponse):
            return False
        clarification_starts = (
            "Total beli ",
            "Nominal biayanya",
            "Jumlah ",
            "Satuan ",
            "Produk apa",
            "Bahan apa",
            "Biaya ini",
            "Ada data",
        )
        return reply.startswith(clarification_starts)

    def _build_action_preview(
        self,
        user_id: int,
        kind: str,
        payload: dict,
        ingredients: list[dict] | None = None,
        products: list[dict] | None = None,
    ) -> str | BotResponse:
        if kind == "sale":
            product = self._catalog_product(payload.get("product", ""), products or [])
            if not product:
                return format_item_not_found("product", payload.get("product", ""), [p.get("name", "") for p in (products or [])])

            quantity = int(payload["quantity"])
            catalog_price = float(product.get("selling_price") or 0)
            total = payload.get("total")
            unit_price = float(total) / quantity if total else catalog_price
            revenue = unit_price * quantity
            warning = ""
            if total and catalog_price > 0:
                diff_ratio = abs(unit_price - catalog_price) / catalog_price
                if diff_ratio >= 0.2:
                    warning = (
                        f"\n⚠️ Harga aktual {format_amount(unit_price)}/item beda dari "
                        f"harga katalog {format_amount(catalog_price)}/item."
                    )

            text = (
                "🧾 <b>Preview Penjualan</b>\n\n"
                f"Produk: <b>{product['name']}</b>\n"
                f"Jumlah: <b>{quantity}</b>\n"
                f"Harga: <b>{format_amount(unit_price)}</b>/item\n"
                f"Total omset: <b>{format_amount(revenue)}</b>"
                f"{warning}\n\n"
                "Catat penjualan ini?"
            )
            payload["product"] = product["name"]
            payload["unit_price"] = unit_price
            if total:
                payload["total"] = int(float(total))

        elif kind == "purchase":
            ingredient_name = payload.get("ingredient", "")
            ingredient = self._catalog_ingredient(ingredient_name, ingredients or [])
            if not ingredient:
                return format_item_not_found(
                    "ingredient",
                    ingredient_name,
                    [(i.get("ingredient") or i.get("name", "")) for i in (ingredients or [])],
                )
            unit = ingredient.get("unit") or ingredient.get("base_unit", "")
            text = (
                "🧾 <b>Preview Pembelian</b>\n\n"
                f"Bahan: <b>{ingredient.get('ingredient') or ingredient.get('name')}</b>\n"
                f"Jumlah: <b>{payload['quantity']:g} {unit}</b>\n"
                f"Total: <b>{format_amount(payload['total'])}</b>\n\n"
                "Catat pembelian ini?"
            )
            payload["ingredient"] = ingredient.get("ingredient") or ingredient.get("name")

        else:
            text = (
                "🧾 <b>Preview Biaya</b>\n\n"
                f"Kategori: <b>{payload['category']}</b>\n"
                f"Nominal: <b>{format_amount(payload['amount'])}</b>\n"
                f"Periode: <b>{payload['period_month']:02d}/{payload['period_year']}</b>\n\n"
                "Catat biaya ini?"
            )

        self.pending.delete_for_user(user_id)
        pending_id = self.pending.save(user_id, kind, payload)
        return BotResponse(
            text=text,
            reply_markup={
                "inline_keyboard": [
                    [{"text": "✅ Catat", "callback_data": f"wolee:batch:confirm:{pending_id}"}],
                    [{"text": "❌ Batal", "callback_data": f"wolee:batch:cancel:{pending_id}"}],
                ]
            },
        )

    def _catalog_product(self, name: str, products: list[dict]) -> dict | None:
        lowered = name.strip().lower()
        for product in products:
            if product.get("name", "").strip().lower() == lowered:
                return product
        return None

    def _catalog_ingredient(self, name: str, ingredients: list[dict]) -> dict | None:
        lowered = name.strip().lower()
        for ingredient in ingredients:
            candidate = (ingredient.get("ingredient") or ingredient.get("name") or "").strip().lower()
            if candidate == lowered:
                return ingredient
        return None

    def _build_batch_preview(
        self,
        user_id: int,
        kind: str,
        result: BatchResolveResult,
        note: str | None = None,
        partners: list[dict] | None = None,
        ingredients: list[dict] | None = None,
        products: list[dict] | None = None,
    ) -> str | BotResponse:
        if result.missing_qty_lines and not result.ok_lines and not result.not_found_lines:
            first = result.missing_qty_lines[0]
            pending_id = self.pending.save(
                user_id,
                "awaiting_qty",
                {
                    "intent": kind,
                    "items": [
                        {
                            "name": line.raw_name,
                            "quantity": line.quantity,
                            "total": line.total,
                            "quantity_unit": line.quantity_unit,
                        }
                        for line in result.lines
                    ],
                    "item_index": result.lines.index(first),
                    "partner_name": result.partner_name,
                    "note": note,
                },
            )
            return f"⚠️ '{first.raw_name}' - jumlah tidak jelas.\nKetik jumlah untuk '{first.raw_name}' (contoh: 10)"

        if not result.ok_lines:
            if kind == "sale_batch":
                available = [p.get("name", "") for p in (products or [])]
                return format_item_not_found("product", "semua item", available)
            available = [(i.get("ingredient") or i.get("name", "")) for i in (ingredients or [])]
            return format_item_not_found("ingredient", "semua item", available)

        preview = format_sale_preview(result) if kind == "sale_batch" else format_purchase_preview(result)

        if result.not_found_lines:
            not_found_names = ", ".join(f"'{line.raw_name}'" for line in result.not_found_lines)
            preview += f"\n\nLanjut tanpa {not_found_names}?"

        payload = {
            "kind": kind,
            "items": [
                {
                    "product_id": line.product_id,
                    "product": line.display_name,
                    "ingredient_id": line.ingredient_id,
                    "ingredient": line.display_name,
                    "quantity": line.quantity,
                    "unit_price": line.unit_price,
                    "total": int(line.subtotal) if line.subtotal else line.total,
                }
                for line in result.ok_lines
            ],
            "note": note,
            "partner_name": result.partner_name,
            "partner_id": result.partner_id,
            "partial": bool(result.not_found_lines),
        }

        pending_id = self.pending.save(user_id, kind, payload)
        buttons = []
        if result.not_found_lines:
            buttons.append([{"text": "⏭ Lanjut tanpa item gagal", "callback_data": f"wolee:batch:partial:{pending_id}"}])
        else:
            buttons.append([{"text": "✅ Konfirmasi", "callback_data": f"wolee:batch:confirm:{pending_id}"}])
        buttons.append([{"text": "❌ Batal", "callback_data": f"wolee:batch:cancel:{pending_id}"}])

        return BotResponse(text=preview, reply_markup={"inline_keyboard": buttons})

    def handle_callback_query(self, user_id: int, callback_data: str) -> str | BotResponse:
        if not callback_data.startswith("wolee:batch:"):
            return "❌ Aksi tidak dikenali."

        parts = callback_data.split(":", 3)
        if len(parts) < 4:
            return "❌ Data callback tidak valid."

        action, pending_id = parts[2], parts[3]
        if action == "cancel":
            self.pending.delete(pending_id, user_id)
            return "Dibatalkan."

        pending = self.pending.get(pending_id, user_id)
        if not pending:
            return "❌ Sesi konfirmasi kedaluwarsa. Kirim ulang laporan."

        if action in {"confirm", "partial"}:
            return self._execute_batch(user_id, pending_id, pending)

        return "❌ Aksi tidak dikenali."

    def _execute_batch(self, user_id: int, pending_id: str, pending: dict) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar."

        payload = pending["payload"]
        kind = pending["kind"]
        note = payload.get("note")

        try:
            if kind == "sale":
                result = client.post_sale(payload)
                data = result["data"]
                self.pending.delete(pending_id, user_id)
                self.storage.touch(user_id)
                return (
                    f"✅ {result['message']}\n"
                    f"Revenue: {format_amount(data['revenue'])} | "
                    f"Profit: {format_amount(data['profit'])}"
                )

            if kind == "purchase":
                result = client.post_transaction(payload)
                data = result["data"]
                self.pending.delete(pending_id, user_id)
                self.storage.touch(user_id)
                return (
                    f"✅ {result['message']}\n"
                    f"Stok {data['ingredient']}: {data['new_stock']} ({data['stock_status']})"
                )

            if kind == "expense":
                result = client.post_expense(payload)
                data = result["data"]
                self.pending.delete(pending_id, user_id)
                self.storage.touch(user_id)
                return (
                    f"✅ {result['message']}\n"
                    f"Biaya {data['category']}: {format_amount(data['amount'])}\n"
                    f"Periode: {data['period_month']:02d}/{data['period_year']}"
                )

            if kind == "sale_batch":
                items = [
                    {
                        "product_id": item.get("product_id"),
                        "product": item.get("product"),
                        "quantity": int(item["quantity"]),
                        "unit_price": item.get("unit_price"),
                    }
                    for item in payload.get("items", [])
                ]
                result = client.post_sales_batch({"items": items, "note": note})
                data = result["data"]
                self.pending.delete(pending_id, user_id)
                self.storage.touch(user_id)
                return (
                    f"✅ {result['message']}\n"
                    f"Total omset: {format_amount(data['total_revenue'])}\n"
                    f"Total profit: {format_amount(data['total_profit'])}"
                )

            items = [
                {
                    "ingredient_id": item.get("ingredient_id"),
                    "ingredient": item.get("ingredient"),
                    "quantity": float(item["quantity"]),
                    "total": int(item.get("total") or 0),
                }
                for item in payload.get("items", [])
            ]
            result = client.post_transactions_batch({"items": items, "note": note})
            data = result["data"]
            self.pending.delete(pending_id, user_id)
            self.storage.touch(user_id)
            return (
                f"✅ {result['message']}\n"
                f"Total pembelian: {format_amount(data['total_amount'])}"
            )
        except WolEeApiError as exc:
            if exc.error_code == "API_ERROR":
                return "⚠️ API tidak tersedia. Coba lagi nanti."
            return f"❌ {exc}"

    def handle_pembelian(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_pembelian(text)
        if not parsed:
            return "Format: beli <bahan> <qty> <harga>\nContoh: beli tepung 2kg 36000"

        try:
            ingredients, _ = self._catalog(client)
        except WolEeApiError:
            ingredients = []
        return self._post_purchase(client, user_id, parsed, ingredients)

    def handle_penjualan(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_penjualan(text)
        if not parsed:
            return "Format: jual <produk> <qty>\nContoh: jual matcha latte 10"

        try:
            _, products = self._catalog(client)
        except WolEeApiError:
            products = []
        return self._post_sale(client, user_id, parsed, products)

    def _not_found_message(self, exc: WolEeApiError, kind: str, search_name: str, catalog: list[dict]) -> str:
        available = exc.payload.get("available_items") or [
            (item.get("name") or item.get("ingredient") or "") for item in catalog
        ]
        dashboard_url = exc.payload.get("dashboard_url")
        return format_item_not_found(kind, search_name, available, dashboard_url)

    def _post_purchase(
        self,
        client: WolEeClient,
        user_id: int,
        parsed: dict,
        ingredients: list[dict],
    ) -> str:
        try:
            result = client.post_transaction(parsed)
            data = result["data"]
            self.storage.touch(user_id)
            return (
                f"✅ {result['message']}\n"
                f"Stok {data['ingredient']}: {data['new_stock']} ({data['stock_status']})"
            )
        except WolEeApiError as exc:
            if exc.error_code == "INGREDIENT_NOT_FOUND":
                name = parsed.get("ingredient", "")
                return self._not_found_message(exc, "ingredient", name, ingredients)
            if exc.error_code == "API_ERROR":
                self.queue.enqueue("post_transaction", parsed)
                return "⚠️ API tidak tersedia. Data disimpan offline, akan dikirim ulang."
            return f"❌ {exc}"

    def _post_sale(
        self,
        client: WolEeClient,
        user_id: int,
        parsed: dict,
        products: list[dict],
    ) -> str:
        try:
            result = client.post_sale(parsed)
            data = result["data"]
            self.storage.touch(user_id)
            return (
                f"✅ {result['message']}\n"
                f"Revenue: {format_amount(data['revenue'])} | "
                f"Profit: {format_amount(data['profit'])}"
            )
        except WolEeApiError as exc:
            if exc.error_code == "PRODUCT_NOT_FOUND":
                name = parsed.get("product", "")
                return self._not_found_message(exc, "product", name, products)
            if exc.error_code == "API_ERROR":
                self.queue.enqueue("post_sale", parsed)
                return "⚠️ API tidak tersedia. Data disimpan offline."
            return f"❌ {exc}"

    def _post_expense(self, client: WolEeClient, user_id: int, parsed: dict) -> str:
        try:
            result = client.post_expense(parsed)
            data = result["data"]
            self.storage.touch(user_id)
            return (
                f"✅ {result['message']}\n"
                f"Biaya {data['category']}: {format_amount(data['amount'])}\n"
                f"Periode: {data['period_month']:02d}/{data['period_year']}"
            )
        except WolEeApiError as exc:
            if exc.error_code == "API_ERROR":
                self.queue.enqueue("post_expense", parsed)
                return "⚠️ API tidak tersedia. Data biaya disimpan offline."
            return f"❌ {exc}"

    def handle_stok(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        ingredient = None
        match = re.search(r"stok\s+(.+)", text, re.IGNORECASE)
        if match:
            ingredient = match.group(1).strip()

        try:
            result = client.get_stock(ingredient)
            lines = []
            for item in result.get("data", []):
                lines.append(
                    f"{item['ingredient']}: {item['current_stock']} {item['unit']} ({item['status']})"
                )
            self.storage.touch(user_id)
            return "📦 Stok:\n" + ("\n".join(lines) if lines else "Tidak ada data.")
        except WolEeApiError:
            return "❌ Gagal mengambil data stok."

    def handle_aging(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            result = client.get_aging()
            summary = result["data"]["summary"]
            self.storage.touch(user_id)
            return (
                f"📊 Aging outstanding: {format_amount(summary['total_outstanding'])}\n"
                f"Partner: {summary['total_partners']}"
            )
        except WolEeApiError:
            return "❌ Gagal mengambil laporan aging."

    def handle_report_pnl(self, user_id: int, month: int, year: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_report_pnl(month, year)["data"]
            self.storage.touch(user_id)
            label = data.get("period_label", f"{month}/{year}")
            expenses = data.get("expenses") or []
            expense_lines = ""
            if expenses:
                top = expenses[:3]
                expense_lines = "\n".join(
                    f"  • {row['category']}: {format_amount(row['amount'])}" for row in top
                )
                expense_lines = f"\n<b>Biaya utama:</b>\n{expense_lines}\n"

            loss_note = ""
            if data["net_profit"] < 0:
                loss_gap = abs(data["net_profit"])
                biggest = expenses[0]["category"] if expenses else "biaya operasional"
                loss_note = (
                    "\n\n⚠️ <b>Kenapa rugi?</b>\n"
                    f"Laba kotor {format_amount(data['gross_profit'])} belum nutup "
                    f"biaya operasional {format_amount(data['total_expenses'])}. "
                    f"Selisihnya {format_amount(loss_gap)}. Biaya terbesar: {biggest}."
                )

            return (
                f"📊 <b>Laporan {label}</b>\n"
                f"<i>(dari data toko)</i>\n\n"
                f"💰 Omset: <b>{format_amount(data['revenue'])}</b>\n"
                f"📉 COGS: <b>{format_amount(data['cogs'])}</b>\n"
                f"📈 Laba kotor: <b>{format_amount(data['gross_profit'])}</b> "
                f"({data['gross_margin']}%)\n"
                f"💸 Biaya operasional: <b>{format_amount(data['total_expenses'])}</b>"
                f"{expense_lines}\n"
                f"✅ <b>Laba bersih: {format_amount(data['net_profit'])}</b> "
                f"({data['net_margin']}%)"
                f"{loss_note}"
            )
        except WolEeApiError:
            return "❌ Gagal mengambil laporan bulanan."

    def handle_stock_alerts(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_stock_alerts()["data"]
            self.storage.touch(user_id)
            alerts = data.get("alerts") or []
            safe = data.get("safe_count", 0)
            if not alerts:
                return f"✅ Semua stok aman ({safe} bahan)."

            lines = [f"⚠️ <b>{len(alerts)} bahan perlu perhatian:</b>\n"]
            for item in alerts:
                emoji = "🔴" if item.get("status") == "kritis" else "🟡"
                lines.append(
                    f"{emoji} {item['ingredient']}: {item['current_stock']} {item['unit']} "
                    f"(min {item['minimum_stock']}) — {item['status']}"
                )
            lines.append(f"\n{safe} bahan lain aman.")
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil alert stok."

    def handle_margin_alerts(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_margin_alerts()["data"]
            self.storage.touch(user_id)
            alerts = data.get("alerts") or []
            if not alerts:
                return "✅ Tidak ada produk dengan margin turun signifikan bulan ini."

            lines = ["📉 <b>Margin turun:</b>\n"]
            for row in alerts[:8]:
                lines.append(
                    f"• {row['product']}: {row['previous_margin']}% → "
                    f"{row['current_margin']}% (↓{row['margin_drop']}%)"
                )
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil alert margin."

    def handle_top_products(self, user_id: int, month: int, year: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_top_products(month, year)["data"]
            self.storage.touch(user_id)
            items = data.get("items") or []
            label = data.get("period_label", f"{month}/{year}")
            if not items:
                return f"Belum ada penjualan di {label}."

            lines = [f"🏆 <b>Produk paling laku — {label}</b>\n"]
            for idx, item in enumerate(items[:5], start=1):
                lines.append(
                    f"{idx}. {item['product']} — {item['quantity']} terjual, "
                    f"omset {format_amount(item['revenue'])}, profit {format_amount(item['profit'])}"
                )
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil produk paling laku."

    def handle_bottom_products(self, user_id: int, month: int, year: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_bottom_products(month, year)["data"]
            self.storage.touch(user_id)
            items = data.get("items") or []
            label = data.get("period_label", f"{month}/{year}")
            if not items:
                return f"Belum ada penjualan di {label}."

            lines = [f"📉 <b>Produk paling sepi — {label}</b>\n"]
            for idx, item in enumerate(items[:5], start=1):
                lines.append(
                    f"{idx}. {item['product']} — {item['quantity']} terjual, "
                    f"omset {format_amount(item['revenue'])}, profit {format_amount(item['profit'])}"
                )
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil produk paling sepi."

    def handle_business_insight(self, user_id: int, month: int, year: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            pnl = client.get_report_pnl(month, year)["data"]
            top = client.get_top_products(month, year, limit=3)["data"].get("items") or []
            bottom = client.get_bottom_products(month, year, limit=3)["data"].get("items") or []
            stock_alerts = client.get_stock_alerts()["data"].get("alerts") or []
            margin_alerts = client.get_margin_alerts()["data"].get("alerts") or []
            self.storage.touch(user_id)
        except WolEeApiError:
            return "❌ Gagal mengambil data strategi."

        label = pnl.get("period_label", f"{month}/{year}")
        lines = [f"🧭 <b>Strategi dari data {label}</b>\n"]

        if pnl["net_profit"] < 0:
            gap = abs(pnl["net_profit"])
            lines.append(
                f"1. Fokus break-even dulu: laba bersih masih {format_amount(pnl['net_profit'])}. "
                f"Laba kotor {format_amount(pnl['gross_profit'])} belum nutup biaya "
                f"{format_amount(pnl['total_expenses'])} (gap {format_amount(gap)})."
            )
        else:
            lines.append(
                f"1. Bisnis sudah profit {format_amount(pnl['net_profit'])}. "
                "Fokusnya pertahankan margin dan dorong produk yang paling cepat muter."
            )

        if top:
            best = top[0]
            lines.append(
                f"2. Dorong produk yang sudah kebukti laku: {best['product']} "
                f"({best['quantity']} terjual, profit {format_amount(best['profit'])})."
            )

        if bottom:
            slow = bottom[0]
            lines.append(
                f"3. Review produk sepi: {slow['product']} baru {slow['quantity']} terjual. "
                "Coba bundling, promo kecil, atau cek apakah perlu diganti menu lain."
            )

        if margin_alerts:
            names = ", ".join(row["product"] for row in margin_alerts[:2])
            lines.append(f"4. Cek margin turun di {names}; harga bahan/resep mungkin perlu dievaluasi.")
        elif stock_alerts:
            names = ", ".join(row["ingredient"] for row in stock_alerts[:3])
            lines.append(f"4. Amankan stok bermasalah: {names}. Jangan sampai produk laku kehabisan bahan.")
        else:
            lines.append("4. Stok dan margin belum menunjukkan alarm besar. Prioritasnya naikin omset.")

        lines.append("\nIni rekomendasi operasional dari data toko, bukan ramalan.")
        return "\n".join(lines)

    def handle_capabilities(self, user_id: int) -> str:
        self.storage.touch(user_id)
        return (
            "🤖 <b>Wol-ee bisa bantu:</b>\n\n"
            "<b>📊 Laporan</b> (gratis, tanpa kuota AI)\n"
            "• profit bulan ini / ringkasan\n"
            "• omset hari ini\n"
            "• barang paling laku\n"
            "• barang paling ga laku\n"
            "• strategi kedepannya\n\n"
            "<b>⚠️ Pantau</b> (gratis)\n"
            "• stok menipis / kritis\n"
            "• margin turun\n\n"
            "<b>✏️ Catat transaksi</b> (pakai kuota AI)\n"
            "• beli tepung Rp 200 ribu\n"
            "• jual matcha 10\n"
            "• copas laporan multi-item\n\n"
            "Tanya langsung pakai bahasa bebas."
        )

    def handle_report_today(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            data = client.get_report_today()["data"]
            self.storage.touch(user_id)
            return (
                f"📊 <b>Laporan Hari Ini</b> ({data['date']})\n\n"
                f"💰 Omset: <b>{format_amount(data['revenue'])}</b>\n"
                f"📉 COGS: <b>{format_amount(data['cogs'])}</b>\n"
                f"📈 Profit: <b>{format_amount(data['profit'])}</b>\n"
                f"📊 Margin: <b>{data['margin']}%</b>\n"
                f"🧾 Transaksi: {data['transactions']}"
            )
        except WolEeApiError:
            return "❌ Gagal mengambil laporan hari ini."

    def handle_history(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            sales = client.list_sales(limit=5).get("data", [])
            purchases = client.list_transactions(limit=5).get("data", [])
            self.storage.touch(user_id)

            lines = ["📜 <b>Riwayat Terbaru</b>\n"]
            if sales:
                lines.append("<b>Penjualan:</b>")
                for s in sales:
                    lines.append(
                        f"• {s.get('product')} x{s.get('quantity')} — "
                        f"{format_amount(s.get('revenue', 0))}"
                    )
            if purchases:
                lines.append("\n<b>Pembelian:</b>")
                for t in purchases:
                    lines.append(
                        f"• {t.get('ingredient')} {t.get('quantity')}{t.get('base_unit', '')} — "
                        f"{format_amount(t.get('total', 0))}"
                    )
            if not sales and not purchases:
                lines.append("Belum ada transaksi.")
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil riwayat."

    def handle_partners(self, user_id: int) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            partners = client.list_partners()
            self.storage.touch(user_id)
            if not partners:
                return f"👥 Belum ada partner. Tambah via {get_app_url()}/partners"

            lines = ["👥 <b>Daftar Partner</b>\n"]
            for p in partners:
                emoji = "👤" if p.get("type") == "customer" else "🏭"
                lines.append(f"{emoji} {p.get('name')} ({p.get('type', '')})")
            return "\n".join(lines)
        except WolEeApiError:
            return "❌ Gagal mengambil daftar partner."

    def _parse_pembelian(self, text: str) -> dict | None:
        match = re.search(
            r"beli\s+(.+?)\s+(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|butir|pcs)?\s+(\d+)",
            text,
            re.IGNORECASE,
        )
        if not match:
            return None

        ingredient, qty, unit, total = match.groups()
        payload: dict = {
            "ingredient": ingredient.strip(),
            "quantity": float(qty.replace(",", ".")),
            "total": int(total),
        }
        if unit:
            from ai_parser import convert_to_base_quantity
            payload["quantity"] = convert_to_base_quantity(payload["quantity"], unit, "g")
        return payload

    def _parse_penjualan(self, text: str) -> dict | None:
        match = re.search(r"jual\s+(.+?)\s+(\d+)\s*$", text, re.IGNORECASE)
        if not match:
            return None

        product, qty = match.groups()
        return {"product": product.strip(), "quantity": int(qty)}
