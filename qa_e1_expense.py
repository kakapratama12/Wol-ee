#!/usr/bin/env python3
"""Reproduce E1: Expense form doesn't submit on staging."""
from playwright.sync_api import sync_playwright
import time

BASE_URL = "https://staging.wolee.my.id"
EMAIL = "owner@wol-ee.local"
PASSWORD = "password"

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    page = ctx.new_page()

    # Capture failed requests
    failed = []
    page.on("response", lambda resp: failed.append(f"{resp.status} {resp.url}") if resp.status >= 400 else None)

    # Capture console errors
    console_errors = []
    page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

    # Login
    page.goto(f"{BASE_URL}/login", wait_until="networkidle")
    page.locator("input#email").fill(EMAIL)
    page.locator("input#password").fill(PASSWORD)
    page.locator("button:has-text('Log in')").first.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

    # Navigate to expenses
    page.goto(f"{BASE_URL}/expenses", wait_until="networkidle")
    page.wait_for_timeout(2000)
    print(f"1. Page URL: {page.url}")
    print(f"2. Title: {page.title()}")

    # Take screenshot of expenses page
    page.screenshot(path="/var/www/wol-ee/qa_screenshots/e1_expenses_page.png", full_page=True)

    # Check if the form exists
    form = page.locator("form").first
    print(f"3. Form exists: {form.is_visible()}")
    
    # Check if the Tambah button exists
    tambah_btn = page.locator("button:has-text('Tambah')").first
    print(f"4. Tambah button visible: {tambah_btn.is_visible()}")

    # Check form fields
    category_select = page.locator("#category")
    amount_input = page.locator("#amount")
    occurred_at_input = page.locator("#occurred_at")
    
    print(f"5. Category select visible: {category_select.is_visible()}")
    print(f"6. Amount input visible: {amount_input.is_visible()}")
    print(f"7. Date input visible: {occurred_at_input.is_visible()}")

    # Fill in the form
    category_select.select_option("operasional")
    page.wait_for_timeout(500)
    
    # Click on amount and type
    amount_input.click()
    amount_input.fill("50000")
    page.wait_for_timeout(500)
    
    # Check what value the amount field has
    amount_value = amount_input.input_value()
    print(f"8. Amount input value after fill: '{amount_value}'")

    # Submit the form
    print("9. Clicking Tambah button...")
    
    # Listen for network requests
    request_made = []
    page.on("request", lambda req: request_made.append(f"{req.method} {req.url}") if "expense" in req.url.lower() else None)
    
    tambah_btn.click()
    page.wait_for_timeout(3000)
    
    print(f"10. Requests made: {request_made}")
    print(f"11. Failed requests: {failed}")
    print(f"12. Console errors: {console_errors}")
    print(f"13. Page URL after submit: {page.url}")
    
    # Check for error messages
    errors = page.locator(".text-destructive").all()
    for i, err in enumerate(errors):
        print(f"14. Error {i}: {err.text_content()}")
    
    # Take screenshot after submit
    page.screenshot(path="/var/www/wol-ee/qa_screenshots/e1_after_submit.png", full_page=True)

    browser.close()
