#!/usr/bin/env python3
"""Smoke test integrasi bot ↔ Wol-ee API."""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

from config import config
from wol_ee_bridge import _handlers, try_handle
from wol_ee_client import WolEeClient


def main() -> int:
    print("1) validate token...")
    payload = WolEeClient.validate_token(config.WOL_EE_API_URL, config.WOL_EE_API_TOKEN)
    tenant = payload["data"]["tenant"]
    print(f"   OK tenant={tenant['name']} (id={tenant['id']})")

    user_id = 999001
    token = config.WOL_EE_API_TOKEN
    _handlers.storage.register(user_id, tenant["id"], token)
    print("2) registered test user")

    print("3) stock...")
    stock = try_handle(user_id, "stok tepung")
    print(f"   {stock.splitlines()[0] if stock else 'FAIL'}")

    print("4) purchase...")
    purchase = try_handle(user_id, "beli tepung 100 2000")
    print(f"   {purchase.splitlines()[0] if purchase else 'FAIL'}")

    print("5) sale...")
    sale = try_handle(user_id, "jual Matcha Latte 1")
    print(f"   {sale.splitlines()[0] if sale else 'FAIL'}")

    print("6) aging...")
    aging = try_handle(user_id, "aging")
    print(f"   {aging.splitlines()[0] if aging else 'FAIL'}")

    if purchase and purchase.startswith("✅") and sale and sale.startswith("✅"):
        print("\nE2E OK")
        return 0

    print("\nE2E FAILED")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
