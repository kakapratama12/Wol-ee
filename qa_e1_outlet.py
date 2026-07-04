#!/usr/bin/env python3
"""Test E1: Multi-outlet expense with outlet_id."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    page = ctx.new_page()

    # Capture all responses
    responses = []
    page.on("response", lambda resp: responses.append({"status": resp.status, "url": resp.url, "headers": dict(resp.headers)}))

    # Login as multi-outlet owner
    page.goto(f"{BASE_URL}/login", wait_until="networkidle")
    page.locator("input#email").fill("owner@wol-ee.local")
    page.locator("input#password").fill("password")
    page.locator("button:has-text('Log in')").first.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

    # Go to expenses
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(1000)

    # Count rows before
    rows_before = page.locator("table tbody tr").count()
    print(f"Rows before: {rows_before}")

    # Fill form
    page.locator("#category").select_option("operasional")
    page.locator("#description").fill("Test biaya outlet QA")
    page.locator("#amount").click()
    page.locator("#amount").fill("50000")
    page.wait_for_timeout(300)

    # Check "Biaya outlet" checkbox
    checkbox = page.locator("#is_outlet_expense")
    print(f"Checkbox visible: {checkbox.is_visible()}")
    checkbox.check()
    page.wait_for_timeout(500)

    # Select outlet
    outlet_select = page.locator("#outlet_id")
    print(f"Outlet select visible: {outlet_select.is_visible()}")
    options = outlet_select.locator("option").all()
    for o in options:
        print(f"  Option: value='{o.get_attribute('value')}' text='{o.text_content()}'")
    
    # Select the first outlet
    outlet_select.select_option(index=1)
    page.wait_for_timeout(300)
    selected_value = outlet_select.input_value()
    print(f"Selected outlet: {selected_value}")

    # Screenshot before submit
    page.screenshot(path="/var/www/wol-ee/qa_screenshots/e1_before_submit.png", full_page=True)

    # Monitor network closely
    post_responses = []
    def on_response(resp):
        if resp.request.method == "POST" and "expense" in resp.url.lower():
            try:
                body = resp.text()
            except:
                body = "(could not read body)"
            post_responses.append({"status": resp.status, "url": resp.url, "body": body[:500]})
    page.on("response", on_response)

    # Submit
    print("\nSubmitting with outlet_id...")
    page.locator("button:has-text('Tambah')").first.click()
    page.wait_for_timeout(4000)

    print(f"\nPOST responses: {post_responses}")

    # Check rows after
    rows_after = page.locator("table tbody tr").count()
    print(f"\nRows after: {rows_after}")
    for i in range(min(rows_after, 5)):
        row_text = page.locator("table tbody tr").nth(i).text_content()
        if row_text:
            print(f"  Row {i}: {row_text.strip()[:120]}")

    # Check for errors
    errors = page.locator(".text-destructive").all()
    for e in errors:
        txt = e.text_content()
        if txt and txt.strip():
            print(f"Error: {txt.strip()}")

    # Check flash
    flash = page.locator("[class*='success'], [role='alert']").all()
    for f in flash:
        print(f"Flash: {f.text_content()}")

    # Screenshot after
    page.screenshot(path="/var/www/wol-ee/qa_screenshots/e1_after_submit_outlet.png", full_page=True)

    browser.close()
