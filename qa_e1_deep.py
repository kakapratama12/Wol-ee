#!/usr/bin/env python3
"""Deep investigation: E1 expense form - check if data actually saves."""
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

    # Navigate to expenses
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(1000)

    # Count existing expenses
    rows_before = page.locator("table tbody tr").count()
    print(f"Expenses before: {rows_before}")
    for i in range(rows_before):
        row_text = page.locator("table tbody tr").nth(i).text_content()
        print(f"  Row {i}: {row_text.strip()[:100]}")

    # Fill form - use "Bahan Baku" category for clarity
    page.locator("#category").select_option("bahan_baku")
    page.locator("#amount").click()
    page.locator("#amount").fill("75000")
    page.wait_for_timeout(300)

    # Check what Inertia form thinks the amount value is
    amount_js = page.evaluate('''() => {
        const appEl = document.getElementById('app');
        if (!appEl) return 'no app element';
        // Inertia stores component state differently, let's try form data inspection
        const input = document.getElementById('amount');
        return input ? input.value : 'no amount input';
    }''')
    print(f"Amount display value: '{amount_js}'")

    # Submit
    print("Submitting...")
    request_info = []
    page.on("request", lambda req: request_info.append({
        "method": req.method, 
        "url": req.url,
        "post_data": req.post_data[:200] if req.post_data else None
    }) if "expense" in req.url.lower() else None)

    page.locator("button:has-text('Tambah')").first.click()
    page.wait_for_timeout(3000)

    print(f"\nRequests made:")
    for r in request_info:
        print(f"  {r['method']} {r['url']}")
        if r['post_data']:
            print(f"  POST data: {r['post_data']}")

    # Check table after
    rows_after = page.locator("table tbody tr").count()
    print(f"\nExpenses after: {rows_after}")
    for i in range(min(rows_after, 5)):
        row_text = page.locator("table tbody tr").nth(i).text_content()
        print(f"  Row {i}: {row_text.strip()[:100]}")

    # Check for flash/success message
    flash = page.locator("[class*='success'], [role='alert']").all()
    for f in flash:
        print(f"Flash/alert: {f.text_content()}")

    # Check if form still has data (might indicate submission failure)
    amount_after = page.locator("#amount").input_value()
    print(f"\nAmount input after submit: '{amount_after}'")

    page.screenshot(path="/var/www/wol-ee/qa_screenshots/e1_deep.png", full_page=True)
    browser.close()
