#!/usr/bin/env python3
"""Test POS sidebar visibility for different roles."""
from playwright.sync_api import sync_playwright

BASE_URL = "https://staging.wolee.my.id"

def login(page, email, password):
    page.goto(f"{BASE_URL}/login", wait_until="networkidle")
    page.locator("input#email").fill(email)
    page.locator("input#password").fill(password)
    page.locator("button:has-text('Log in')").first.click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)

def check_pos_sidebar(page, label):
    """Check if POS link is visible in sidebar."""
    pos_link = page.locator("a[href='/pos']")
    count = pos_link.count()
    visible = pos_link.first.is_visible() if count > 0 else False
    print(f"  {label}: POS link count={count}, visible={visible}")
    return visible

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])

    # 1. Single-outlet owner (Chockles)
    print("=== Single-outlet owner (Chockles) ===")
    ctx1 = browser.new_context(viewport={"width": 1280, "height": 800})
    p1 = ctx1.new_page()
    login(p1, "owner@chockles.test", "password")
    p1.goto(f"{BASE_URL}/dashboard", wait_until="networkidle")
    p1.wait_for_timeout(1000)
    check_pos_sidebar(p1, "Owner single-outlet")
    p1.close()
    ctx1.close()

    # 2. Multi-outlet owner (Kafe Contoh)
    print("\n=== Multi-outlet owner (Kafe Contoh) ===")
    ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
    p2 = ctx2.new_page()
    login(p2, "owner@wol-ee.local", "password")
    p2.goto(f"{BASE_URL}/dashboard", wait_until="networkidle")
    p2.wait_for_timeout(1000)
    check_pos_sidebar(p2, "Owner multi-outlet")
    p2.close()
    ctx2.close()

    # 3. Multi-outlet staff (kasir)
    print("\n=== Multi-outlet staff (kasir) ===")
    ctx3 = browser.new_context(viewport={"width": 1280, "height": 800})
    p3 = ctx3.new_page()
    login(p3, "kasir@wol-ee.local", "password")
    p3.goto(f"{BASE_URL}/dashboard", wait_until="networkidle")
    p3.wait_for_timeout(1000)
    check_pos_sidebar(p3, "Staff multi-outlet")
    p3.close()
    ctx3.close()

    # 4. Single-outlet staff (kasir)
    print("\n=== Single-outlet staff (kasir) ===")
    ctx4 = browser.new_context(viewport={"width": 1280, "height": 800})
    p4 = ctx4.new_page()
    login(p4, "kasir@chockles.test", "password")
    p4.goto(f"{BASE_URL}/dashboard", wait_until="networkidle")
    p4.wait_for_timeout(1000)
    check_pos_sidebar(p4, "Staff single-outlet")
    p4.close()
    ctx4.close()

    browser.close()
