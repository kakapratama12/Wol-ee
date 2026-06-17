"""Handler pesan bot — integrasi Wol-ee API dengan NL parsing."""

from __future__ import annotations

import asyncio
import logging
import re
from typing import Callable

from ai_parser import (
    format_amount,
    parse_wolee_inventory,
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
            self.storage.register(user_id, tenant["id"], token)
            return (
                f"Berhasil terdaftar ke {tenant['name']}!\n\n"
                "Kamu bisa ketik bahasa natural, contoh:\n"
                "• Beli tepung Rp 200 ribu\n"
                "• Jual matcha latte 10\n"
                "• Copas laporan penjualan multi-item\n"
                "• /profit — profit hari ini\n"
                "• /history — riwayat transaksi"
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
        if not active or active["kind"] != "awaiting_qty":
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

    async def handle_natural_language(self, user_id: int, text: str, is_pro: bool = False) -> str | BotResponse:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        try:
            ingredients, products = await asyncio.to_thread(self._catalog, client)
        except WolEeApiError:
            return "❌ Gagal mengambil katalog dari API."

        parsed = await parse_wolee_inventory(text, ingredients, products, is_pro=is_pro)
        if "error" in parsed:
            return f"❌ {parsed['error']}"

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
            payload = to_api_purchase_payload(parsed, ingredients)
            if not payload:
                return "❌ Data pembelian tidak lengkap. Sebutkan bahan dan nominal."
            return self._post_purchase(client, user_id, payload, ingredients)
        if intent == "sale":
            payload = to_api_sale_payload(parsed)
            if not payload:
                return "❌ Data penjualan tidak lengkap. Sebutkan produk dan jumlah."
            return self._post_sale(client, user_id, payload, products)

        return (
            "❌ Perintah tidak dikenali.\n\n"
            "Contoh:\n"
            "• Beli tepung 2kg Rp 36 ribu\n"
            "• Jual matcha latte 10\n"
            "• matcha 10, croissant 5, latte 8\n"
            "• stok tepung"
        )

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
