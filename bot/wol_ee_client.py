"""HTTP client untuk Wol-ee Laravel API."""

from __future__ import annotations

import logging
from typing import Any

import requests

logger = logging.getLogger(__name__)


class WolEeApiError(Exception):
    def __init__(self, message: str, error_code: str | None = None, payload: dict | None = None):
        super().__init__(message)
        self.error_code = error_code
        self.payload = payload or {}


class WolEeClient:
    def __init__(self, base_url: str, token: str, timeout: int = 15):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        })

    def _request(self, method: str, path: str, **kwargs) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        try:
            response = self.session.request(method, url, timeout=self.timeout, **kwargs)
        except requests.Timeout as exc:
            raise WolEeApiError("API timeout", "API_ERROR") from exc
        except requests.RequestException as exc:
            raise WolEeApiError("Koneksi API gagal", "API_ERROR") from exc

        try:
            payload = response.json()
        except ValueError:
            raise WolEeApiError("Respons API tidak valid", "API_ERROR")

        if not response.ok or not payload.get("success", False):
            raise WolEeApiError(
                payload.get("message", "Permintaan gagal"),
                payload.get("error_code", "API_ERROR"),
                payload,
            )

        return payload

    def _request_plain(self, method: str, path: str, **kwargs) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        try:
            response = self.session.request(method, url, timeout=self.timeout, **kwargs)
        except requests.Timeout as exc:
            raise WolEeApiError("API timeout", "API_ERROR") from exc
        except requests.RequestException as exc:
            raise WolEeApiError("Koneksi API gagal", "API_ERROR") from exc

        try:
            payload = response.json()
        except ValueError:
            raise WolEeApiError("Respons API tidak valid", "API_ERROR")

        if not response.ok:
            raise WolEeApiError(
                payload.get("message", "Permintaan gagal"),
                payload.get("error_code", "API_ERROR"),
                payload,
            )

        return payload

    @classmethod
    def validate_token(cls, base_url: str, token: str, timeout: int = 15) -> dict[str, Any]:
        url = f"{base_url.rstrip('/')}/bot/validate-token"
        try:
            response = requests.post(
                url,
                json={"token": token},
                timeout=timeout,
                headers={"Accept": "application/json"},
            )
            payload = response.json()
        except requests.RequestException as exc:
            raise WolEeApiError("Koneksi API gagal", "API_ERROR") from exc

        if not response.ok or not payload.get("success"):
            raise WolEeApiError(payload.get("message", "Token tidak valid"), "UNAUTHORIZED", payload)

        return payload

    def post_transaction(self, data: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/transactions", json=data)

    def post_transactions_batch(self, data: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/transactions/batch", json=data)

    def post_sale(self, data: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/sales", json=data)

    def post_sales_batch(self, data: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/sales/batch", json=data)

    def get_stock(self, ingredient: str | None = None) -> dict[str, Any]:
        params = {"ingredient": ingredient} if ingredient else None
        return self._request("GET", "/stock", params=params)

    def get_report_today(self) -> dict[str, Any]:
        return self._request("GET", "/reports/today")

    def get_report_pnl(self, month: int, year: int) -> dict[str, Any]:
        return self._request("GET", "/reports/pnl", params={"month": month, "year": year})

    def get_stock_alerts(self) -> dict[str, Any]:
        return self._request("GET", "/reports/stock-alerts")

    def get_margin_alerts(self) -> dict[str, Any]:
        return self._request("GET", "/reports/margin-alerts")

    def get_top_products(self, month: int, year: int, limit: int = 5) -> dict[str, Any]:
        return self._request(
            "GET",
            "/reports/top-products",
            params={"month": month, "year": year, "limit": limit},
        )

    def get_bottom_products(self, month: int, year: int, limit: int = 5) -> dict[str, Any]:
        return self._request(
            "GET",
            "/reports/bottom-products",
            params={"month": month, "year": year, "limit": limit},
        )

    def get_ai_usage(self, telegram_user_id: int) -> dict[str, Any]:
        return self._request("GET", "/bot/usage", params={"telegram_user_id": telegram_user_id})

    def consume_ai_quota(self, telegram_user_id: int) -> dict[str, Any]:
        return self._request("POST", "/bot/ai-usage", json={"telegram_user_id": telegram_user_id})

    def post_feedback(
        self,
        telegram_user_id: int,
        feedback_text: str,
        original_message: str | None = None,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "telegram_user_id": telegram_user_id,
            "feedback_text": feedback_text,
        }
        if original_message:
            payload["original_message"] = original_message
        return self._request("POST", "/bot/feedback", json=payload)

    def get_aging(self) -> dict[str, Any]:
        return self._request("GET", "/reports/aging")

    def list_transactions(self, limit: int = 10, date: str | None = None) -> dict[str, Any]:
        params: dict[str, Any] = {"limit": limit}
        if date:
            params["date"] = date
        return self._request("GET", "/transactions", params=params)

    def list_sales(self, limit: int = 10, date: str | None = None) -> dict[str, Any]:
        params: dict[str, Any] = {"limit": limit}
        if date:
            params["date"] = date
        return self._request("GET", "/sales", params=params)

    def list_products(self) -> dict[str, Any]:
        return self._request("GET", "/products")

    def list_partners(self, partner_type: str | None = None) -> list[dict[str, Any]]:
        params = {"type": partner_type} if partner_type else None
        payload = self._request_plain("GET", "/partners", params=params)
        return payload.get("data", [])
