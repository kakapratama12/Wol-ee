#!/usr/bin/env python3
"""Quick check: F2 staff access, stock crash, C7/C8 fixes on staging."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

def login(page, email, password, is_pos=False):
    url = f"{BASE_URL}/pos/login" if is_pos else f"{BASE_URL}/login"
    page.goto(url, wait_until="networkidle")
    page.locator("input#email").fill(email)
    page.locator("input#password").fill(password)
    btn = page.get_by_role("button", name="Log in").first
    btn.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])

    # F2: Staff cannot access /staff
    print("=== F2: Staff /staff access ===")
    ctx1 = browser.new_context(viewport={"width": 1280, "height": 800})
    p1 = ctx1.new_page()
    login(p1, "kasir@wol-ee.local", "password")
    p1.goto(f"{BASE_URL}/staff", wait_until="networkidle")
    p1.wait_for_timeout(1000)
    print(f"  URL: {p1.url}")
    is_403 = "403" in p1.url or "403" in p1.content() or "unauthorized" in p1.content().lower() or "forbidden" in p1.content().lower()
    print(f"  403/block: {is_403}")
    p1.screenshot(path="/var/www/wol-ee/qa_screenshots/f2_staff_staff.png", full_page=True)
    p1.close()
    ctx1.close()

    # Stock crash check — POS stock pages
    print("\n=== Stock pages (kasir POS) ===")
    ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
    p2 = ctx2.new_page()
    login(p2, "kasir@wol-ee.local", "password", is_pos=True)
    
    failed = []
    p2.on("response", lambda r: failed.append(f"{r.status} {r.url}") if r.status >= 500 else None)
    
    for path in ["/pos/stock", "/pos/stock/adjust", "/pos/stock/purchase", "/pos/stock/movements"]:
        p2.goto(f"{BASE_URL}{path}", wait_until="networkidle")
        p2.wait_for_timeout(1500)
        title = p2.title()
        content_len = len(p2.content())
        print(f"  {path}: title='{title}' content_len={content_len}")
    
    print(f"  500 errors: {failed}")
    p2.close()
    ctx2.close()

    # C8: Check PaymentModal — does it show "Rp 0" initially?
    print("\n=== C8: PaymentModal check ===")
    ctx3 = browser.new_context(viewport={"width": 1280, "height": 800})
    p3 = ctx3.new_page()
    login(p3, "kasir@wol-ee.local", "password", is_pos=True)
    p3.goto(f"{BASE_URL}/pos/entry", wait_until="networkidle")
    p3.wait_for_timeout(2000)
    
    # Check POS page content
    content = p3.content()
    has_rp0 = "Rp 0" in content and "Rp 0." not in content  # "Rp 0" but not "Rp 0.000"
    print(f"  POS entry loaded: {'Rp 0' in p3.content()}")
    p3.screenshot(path="/var/www/wol-ee/qa_screenshots/c8_pos_entry.png", full_page=True)
    p3.close()
    ctx3.close()

    browser.close()
