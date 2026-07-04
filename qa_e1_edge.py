#!/usr/bin/env python3
"""Test E1 edge cases: empty amount, staff account, single-outlet account."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

def test_expense_edge_cases(page, label):
    """Test various edge cases for the expense form."""
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(1000)
    
    # Test 1: Submit with empty amount
    print(f"\n--- {label}: Test empty amount ---")
    page.locator("#category").select_option("operasional")
    page.locator("#amount").fill("")  # leave empty
    page.wait_for_timeout(300)
    
    request_info = []
    page.on("request", lambda req: request_info.append(req.method) if "expense" in req.url.lower() else None)
    
    page.locator("button:has-text('Tambah')").first.click()
    page.wait_for_timeout(2000)
    
    print(f"  Requests after empty submit: {request_info}")
    errors = page.locator(".text-destructive").all()
    for e in errors:
        txt = e.text_content()
        if txt and txt.strip():
            print(f"  Validation error: {txt.strip()}")
    page.remove_listener("request", lambda req: None)

    # Test 2: Submit with amount "0"
    print(f"\n--- {label}: Test zero amount ---")
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(1000)
    page.locator("#category").select_option("operasional")
    page.locator("#amount").click()
    page.locator("#amount").fill("0")
    page.wait_for_timeout(300)
    
    request_info2 = []
    page.on("request", lambda req: request_info2.append(f"{req.method} {req.url}") if "expense" in req.url.lower() else None)
    
    page.locator("button:has-text('Tambah')").first.click()
    page.wait_for_timeout(2000)
    
    print(f"  Requests: {request_info2}")
    rows = page.locator("table tbody tr").count()
    print(f"  Table rows: {rows}")

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
    
    # Test as multi-outlet owner
    ctx1 = browser.new_context(viewport={"width": 1280, "height": 800})
    p1 = ctx1.new_page()
    p1.goto(f"{BASE_URL}/login", wait_until="networkidle")
    p1.locator("input#email").fill("owner@wol-ee.local")
    p1.locator("input#password").fill("password")
    p1.locator("button:has-text('Log in')").first.click()
    p1.wait_for_load_state("networkidle")
    p1.wait_for_timeout(2000)
    test_expense_edge_cases(p1, "Multi-Outlet Owner")
    p1.close()
    ctx1.close()
    
    # Test as single-outlet owner
    ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
    p2 = ctx2.new_page()
    p2.goto(f"{BASE_URL}/login", wait_until="networkidle")
    p2.locator("input#email").fill("owner@chockles.test")
    p2.locator("input#password").fill("password")
    p2.locator("button:has-text('Log in')").first.click()
    p2.wait_for_load_state("networkidle")
    p2.wait_for_timeout(2000)
    test_expense_edge_cases(p2, "Single-Outlet Owner")
    p2.close()
    ctx2.close()
    
    # Test as staff (kasir)
    ctx3 = browser.new_context(viewport={"width": 1280, "height": 800})
    p3 = ctx3.new_page()
    p3.goto(f"{BASE_URL}/login", wait_until="networkidle")
    p3.locator("input#email").fill("kasir@wol-ee.local")
    p3.locator("input#password").fill("password")
    p3.locator("button:has-text('Log in')").first.click()
    p3.wait_for_load_state("networkidle")
    p3.wait_for_timeout(2000)
    
    # Staff - check if they can access /expenses
    p3.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    p3.wait_for_timeout(2000)
    print(f"\n--- Kasir (Staff) ---")
    print(f"  URL after navigating to /expenses: {p3.url}")
    print(f"  Page title: {p3.title()}")
    p3.close()
    ctx3.close()
    
    browser.close()
