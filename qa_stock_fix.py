#!/usr/bin/env python3
"""Test stock pages for both single and multi outlet."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

def login(page, email, password):
    page.goto(f"{BASE_URL}/login", wait_until="networkidle")
    page.locator("input#email").fill(email)
    page.locator("input#password").fill(password)
    page.locator("button:has-text('Log in')").first.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])

    # === SINGLE OUTLET (Chockles) ===
    print("=== SINGLE OUTLET (Chockles) ===")
    ctx1 = browser.new_context(viewport={"width": 1280, "height": 800})
    p1 = ctx1.new_page()
    login(p1, "kasir@chockles.test", "password")

    for path in ["/pos/stock", "/pos/stock/purchase", "/pos/stock/adjust", "/pos/stock/movements"]:
        p1.goto(f"{BASE_URL}{path}", wait_until="networkidle")
        p1.wait_for_timeout(1500)
        title = p1.title()
        url = p1.url
        redirected = "/pos" == url.replace(BASE_URL, "") and path != "/pos"
        print(f"  {path}: title='{title}' redirected={redirected}")

    p1.close()
    ctx1.close()

    # === MULTI OUTLET (Kafe Contoh) ===
    print("\n=== MULTI OUTLET (Kafe Contoh) ===")
    ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
    p2 = ctx2.new_page()
    login(p2, "kasir@wol-ee.local", "password")

    for path in ["/pos/stock", "/pos/stock/purchase", "/pos/stock/adjust", "/pos/stock/movements"]:
        p2.goto(f"{BASE_URL}{path}", wait_until="networkidle")
        p2.wait_for_timeout(1500)
        title = p2.title()
        url = p2.url
        redirected = "/pos" == url.replace(BASE_URL, "") and path != "/pos"
        print(f"  {path}: title='{title}' redirected={redirected}")

    p2.close()
    ctx2.close()

    browser.close()
