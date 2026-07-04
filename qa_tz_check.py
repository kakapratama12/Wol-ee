#!/usr/bin/env python3
"""Verify timezone fix on staging."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    page = ctx.new_page()

    # Login
    page.goto(f"{BASE_URL}/login", wait_until="networkidle")
    page.locator("input#email").fill("owner@wol-ee.local")
    page.locator("input#password").fill("password")
    page.locator("button:has-text('Log in')").first.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

    # Check expenses page — dates should show WIB
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(1000)
    
    # Get table rows
    rows = page.locator("table tbody tr").all()
    print("Expense dates (should be WIB):")
    for i, row in enumerate(rows[:5]):
        cells = row.locator("td").all()
        if len(cells) >= 2:
            date_text = cells[1].text_content()
            print(f"  Row {i}: {date_text}")
    
    # Check sales page
    page.goto(f"{BASE_URL}/sales", wait_until="networkidle")
    page.wait_for_timeout(1000)
    
    rows2 = page.locator("table tbody tr").all()
    print("\nSales dates (should be WIB):")
    for i, row in enumerate(rows2[:5]):
        cells = row.locator("td").all()
        if len(cells) >= 2:
            date_text = cells[1].text_content()
            print(f"  Row {i}: {date_text}")

    # Check POS entry — date default should be WIB
    ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
    p2 = ctx2.new_page()
    p2.goto(f"{BASE_URL}/login", wait_until="networkidle")
    p2.locator("input#email").fill("kasir@wol-ee.local")
    p2.locator("input#password").fill("password")
    p2.locator("button:has-text('Log in')").first.click()
    p2.wait_for_load_state("networkidle")
    p2.wait_for_timeout(2000)
    p2.goto(f"{BASE_URL}/pos/entry", wait_until="networkidle")
    p2.wait_for_timeout(1000)
    
    # Check the "Hari Ini" page for date display
    content = p2.content()
    if "Jul" in content or "Jul" in content:
        # Extract visible dates
        dates = p2.locator("text=/\\d{1,2}\\s(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)/i").all()
        for d in dates[:5]:
            print(f"  POS date: {d.text_content()}")

    browser.close()
