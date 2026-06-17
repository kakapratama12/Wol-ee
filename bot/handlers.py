"""Handler pesan bot — integrasi Wol-ee API dengan NL parsing."""

from __future__ import annotations

import asyncio
import logging
import re
from typing import Callable

from ai_parser import format_amount, parse_wolee_inventory, to_api_purchase_payload, to_api_sale_payload
from bot_storage import BotUserStorage
from offline_queue import OfflineQueue
from wol_ee_client import WolEeApiError, WolEeClient

logger = logging.getLogger(__name__)


class BotHandlers:
    def __init__(
        self,
        api_url: str,
        default_token: str,
        storage: BotUserStorage,
        queue: OfflineQueue,
        client_factory: Callable[[str], WolEeClient] | None = None,
    ):
        self.api_url = api_url
        self.default_token = default_token
        self.storage = storage
        self.queue = queue
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

    async def handle_natural_language(self, user_id: int, text: str, is_pro: bool = False) -> str:
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
        if intent == "purchase":
            payload = to_api_purchase_payload(parsed, ingredients)
            if not payload:
                return "❌ Data pembelian tidak lengkap. Sebutkan bahan dan nominal."
            return self._post_purchase(client, user_id, payload)
        if intent == "sale":
            payload = to_api_sale_payload(parsed)
            if not payload:
                return "❌ Data penjualan tidak lengkap. Sebutkan produk dan jumlah."
            return self._post_sale(client, user_id, payload)

        return (
            "❌ Perintah tidak dikenali.\n\n"
            "Contoh:\n"
            "• Beli tepung 2kg Rp 36 ribu\n"
            "• Jual matcha latte 10\n"
            "• stok tepung"
        )

    def handle_pembelian(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_pembelian(text)
        if not parsed:
            return "Format: beli <bahan> <qty> <harga>\nContoh: beli tepung 2kg 36000"

        return self._post_purchase(client, user_id, parsed)

    def handle_penjualan(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_penjualan(text)
        if not parsed:
            return "Format: jual <produk> <qty>\nContoh: jual matcha latte 10"

        return self._post_sale(client, user_id, parsed)

    def _post_purchase(self, client: WolEeClient, user_id: int, parsed: dict) -> str:
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
                suggestions = exc.payload.get("suggestions", [])
                hint = f" Maksudnya: {', '.join(suggestions)}?" if suggestions else ""
                return f"❌ {exc}{hint}"
            if exc.error_code == "API_ERROR":
                self.queue.enqueue("post_transaction", parsed)
                return "⚠️ API tidak tersedia. Data disimpan offline, akan dikirim ulang."
            return f"❌ {exc}"

    def _post_sale(self, client: WolEeClient, user_id: int, parsed: dict) -> str:
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
                suggestions = exc.payload.get("suggestions", [])
                hint = f" Maksudnya: {', '.join(suggestions)}?" if suggestions else ""
                return f"❌ {exc}{hint}"
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
                return "👥 Belum ada partner. Tambah via dashboard Wol-ee."

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
