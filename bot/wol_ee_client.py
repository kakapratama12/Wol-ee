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

    def post_sale(self, data: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/sales", json=data)

    def get_stock(self, ingredient: str | None = None) -> dict[str, Any]:
        params = {"ingredient": ingredient} if ingredient else None
        return self._request("GET", "/stock", params=params)

    def get_aging(self) -> dict[str, Any]:
        return self._request("GET", "/reports/aging")
