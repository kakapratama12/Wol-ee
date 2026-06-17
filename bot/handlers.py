"""Handler pesan bot — integrasi dengan Wol-ee API."""

from __future__ import annotations

import logging
import re
from typing import Callable

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
            return f"Berhasil terdaftar ke {tenant['name']}! Ketik 'bantuan' untuk lihat perintah."
        except WolEeApiError:
            return "Token tidak valid. Minta Owner generate token di dashboard > Bot Integration."

    def _client_for(self, user_id: int) -> WolEeClient | None:
        token = self.storage.get_token(user_id) or self.default_token
        if not token:
            return None
        return self.client_factory(token)

    def handle_pembelian(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_pembelian(text)
        if not parsed:
            return "Format: beli <bahan> <qty> <harga>\nContoh: beli tepung 2kg 36000"

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

    def handle_penjualan(self, user_id: int, text: str) -> str:
        client = self._client_for(user_id)
        if client is None:
            return "Belum terdaftar. Ketik /start <token> dulu."

        parsed = self._parse_penjualan(text)
        if not parsed:
            return "Format: jual <produk> <qty>\nContoh: jual matcha latte 10"

        try:
            result = client.post_sale(parsed)
            data = result["data"]
            self.storage.touch(user_id)
            return (
                f"✅ {result['message']}\n"
                f"Revenue: Rp {data['revenue']:,.0f} | Profit: Rp {data['profit']:,.0f}"
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
                f"📊 Aging outstanding: Rp {summary['total_outstanding']:,.0f}\n"
                f"Partner: {summary['total_partners']}"
            )
        except WolEeApiError:
            return "❌ Gagal mengambil laporan aging."

    def _parse_pembelian(self, text: str) -> dict | None:
        match = re.search(
            r"beli\s+(.+?)\s+(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|butir|pcs)?\s+(\d+)",
            text,
            re.IGNORECASE,
        )
        if not match:
            return None

        ingredient, qty, _unit, total = match.groups()
        return {
            "ingredient": ingredient.strip(),
            "quantity": float(qty.replace(",", ".")),
            "total": int(total),
        }

    def _parse_penjualan(self, text: str) -> dict | None:
        match = re.search(r"jual\s+(.+?)\s+(\d+)\s*$", text, re.IGNORECASE)
        if not match:
            return None

        product, qty = match.groups()
        return {"product": product.strip(), "quantity": int(qty)}
