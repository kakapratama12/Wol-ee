#!/usr/bin/env python3
"""QA Script: Single Outlet (Chockles) — Exploratory Testing"""

import json
import os
import sys
from datetime import datetime
from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

BASE_URL = "https://staging.wolee.my.id"
SCREENSHOTS_DIR = "/var/www/wol-ee/qa_screenshots"
FINDINGS = []

os.makedirs(SCREENSHOTS_DIR, exist_ok=True)

def log(msg):
    print(f"  → {msg}")

def screenshot(page, name):
    path = f"{SCREENSHOTS_DIR}/{name}.png"
    page.screenshot(path=path, full_page=True)
    log(f"Screenshot: {path}")
    return path

def add_finding(severity, title, steps, expected, actual, screenshot_path=None):
    finding = {
        "severity": severity,
        "title": title,
        "steps": steps,
        "expected": expected,
        "actual": actual,
        "screenshot": screenshot_path,
        "timestamp": datetime.now().isoformat(),
    }
    FINDINGS.append(finding)
    print(f"\n{'='*60}")
    print(f"[{severity.upper()}] {title}")
    print(f"  Steps: {steps}")
    print(f"  Expected: {expected}")
    print(f"  Actual: {actual}")
    if screenshot_path:
        print(f"  Screenshot: {screenshot_path}")
    print(f"{'='*60}\n")

def test_login(page, email, password, role_name):
    """Test login flow"""
    print(f"\n{'#'*60}")
    print(f"# Testing Login as {role_name} ({email})")
    print(f"{'#'*60}")
    
    page.goto(f"{BASE_URL}/login")
    page.wait_for_load_state("networkidle")
    
    # Check login page loads
    if "login" in page.url.lower() or page.locator("input[type='email'], input[name='email']").count() > 0:
        log("Login page loaded OK")
    else:
        add_finding("CRITICAL", "Login page not accessible",
            "Navigate to /login", "Login form visible", f"URL: {page.url}")
        return False
    
    screenshot(page, f"login_{role_name}_page")
    
    # Fill credentials
    email_input = page.locator("input[type='email'], input[name='email']").first
    password_input = page.locator("input[type='password'], input[name='password']").first
    
    email_input.fill(email)
    password_input.fill(password)
    
    # Submit — button text is "Log in" with no type attr
    submit_btn = page.locator("button:has-text('Log in'), button:has-text('Masuk'), button[type='submit']").first
    submit_btn.click()
    
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)
    
    # Check if login successful
    if "/login" not in page.url.lower():
        log(f"Login successful! Redirected to: {page.url}")
        screenshot(page, f"login_{role_name}_success")
        return True
    else:
        # Check for error message
        error_text = page.locator(".text-destructive, .alert-danger, [role='alert']").first.text_content() if page.locator(".text-destructive, .alert-danger, [role='alert']").count() > 0 else "No error message"
        add_finding("CRITICAL", f"Login failed for {role_name}",
            f"Login with {email}/password", "Redirect to dashboard", f"Still on login page. Error: {error_text}")
        screenshot(page, f"login_{role_name}_failed")
        return False

def test_dashboard(page, role_name):
    """Test dashboard page"""
    print(f"\n{'#'*60}")
    print(f"# Testing Dashboard ({role_name})")
    print(f"{'#'*60}")
    
    page.goto(f"{BASE_URL}/dashboard")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)
    
    screenshot(page, f"dashboard_{role_name}")
    
    # Check for key elements
    checks = [
        ("Revenue/omset display", ".text-2xl, .text-3xl, [class*='font-bold']"),
        ("Navigation menu", "nav, [role='navigation']"),
    ]
    
    for check_name, selector in checks:
        count = page.locator(selector).count()
        if count > 0:
            log(f"{check_name}: Found ({count} elements)")
        else:
            add_finding("MEDIUM", f"Dashboard: {check_name} missing",
                "View dashboard", f"{check_name} should be visible", "Not found")
    
    # Check for console errors
    errors = []
    page.on("console", lambda msg: errors.append(msg.text) if msg.type == "error" else None)
    page.wait_for_timeout(1000)
    
    if errors:
        add_finding("HIGH", "Console errors on dashboard",
            "Load dashboard", "No console errors", f"Errors: {errors[:3]}")

def test_page(page, path, name, role_name):
    """Generic page test"""
    print(f"\n{'#'*60}")
    print(f"# Testing {name} ({role_name})")
    print(f"{'#'*60}")
    
    page.goto(f"{BASE_URL}{path}")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1500)
    
    ss = screenshot(page, f"{name}_{role_name}")
    
    # Check for blank page
    body_text = page.locator("body").text_content()
    if len(body_text.strip()) < 50:
        add_finding("CRITICAL", f"{name}: Page appears blank",
            f"Navigate to {path}", "Page should have content", f"Body text: {body_text[:100]}")
        return
    
    # Check for error pages
    if "error" in body_text.lower() or "exception" in body_text.lower():
        add_finding("CRITICAL", f"{name}: Error page displayed",
            f"Navigate to {path}", "Page should load normally", f"Error content detected")
        return
    
    log(f"{name} loaded OK ({len(body_text)} chars)")

def test_expense_flow(page, role_name):
    """Test expense creation with outlet checkbox"""
    print(f"\n{'#'*60}")
    print(f"# Testing Expense Flow ({role_name})")
    print(f"{'#'*60}")
    
    page.goto(f"{BASE_URL}/expenses")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)
    
    ss = screenshot(page, f"expense_list_{role_name}")
    
    # Check for outlet checkbox
    checkbox = page.locator("input#is_outlet_expense, input[type='checkbox']")
    if checkbox.count() > 0:
        log(f"Outlet checkbox found: {checkbox.count()} checkboxes")
    else:
        add_finding("MEDIUM", "Expense: Outlet checkbox not found",
            "View expense form", "Checkbox 'Biaya outlet' should exist", "Not found")
    
    # Try adding expense WITHOUT outlet
    try:
        # Fill form
        page.locator("select#category, select[name='category']").first.select_option("operasional")
        page.locator("input#description, input[name='description']").first.fill("Test biaya QA")
        page.locator("input#amount, input[name='amount']").first.fill("50000")
        
        screenshot(page, f"expense_form_filled_{role_name}")
        
        # Submit
        page.locator("button:has-text('Tambah')").first.click()
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1000)
        
        # Check success
        body = page.locator("body").text_content()
        if "test biaya qa" in body.lower() or "50" in body:
            log("Expense without outlet: Created successfully")
            screenshot(page, f"expense_created_no_outlet_{role_name}")
        else:
            add_finding("HIGH", "Expense: Failed to create expense without outlet",
                "Fill form and submit", "Expense should appear in list", "Not found in list")
    except Exception as e:
        add_finding("HIGH", f"Expense: Error creating expense - {str(e)[:100]}",
            "Fill and submit expense form", "Success", f"Exception: {str(e)[:200]}")
    
    # Try adding expense WITH outlet
    try:
        # Check outlet checkbox
        checkbox = page.locator("input#is_outlet_expense").first
        if checkbox.count() > 0:
            checkbox.check()
            page.wait_for_timeout(500)
            
            # Check if outlet select appeared
            outlet_select = page.locator("select#outlet_id, select[name='outlet_id']")
            if outlet_select.count() > 0:
                log("Outlet select appeared after checkbox")
                outlet_select.first.select_option(index=1)  # Select first outlet
            else:
                add_finding("HIGH", "Expense: Outlet select not shown after checkbox",
                    "Check 'Biaya outlet' checkbox", "Outlet dropdown should appear", "Not found")
            
            # Fill form
            page.locator("input#description, input[name='description']").first.fill("Test biaya outlet QA")
            page.locator("input#amount, input[name='amount']").first.fill("75000")
            
            screenshot(page, f"expense_form_outlet_{role_name}")
            
            # Submit
            page.locator("button:has-text('Tambah')").first.click()
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(1000)
            
            body = page.locator("body").text_content()
            if "test biaya outlet qa" in body.lower():
                log("Expense with outlet: Created successfully")
                screenshot(page, f"expense_created_outlet_{role_name}")
            else:
                add_finding("HIGH", "Expense: Failed to create expense with outlet",
                    "Check outlet, fill form, submit", "Expense should appear in list", "Not found")
    except Exception as e:
        add_finding("HIGH", f"Expense: Error with outlet flow - {str(e)[:100]}",
            "Check outlet checkbox, fill form, submit", "Success", f"Exception: {str(e)[:200]}")

def test_pos_flow(page, role_name):
    """Test POS flow as staff"""
    print(f"\n{'#'*60}")
    print(f"# Testing POS Flow ({role_name})")
    print(f"{'#'*60}")
    
    # Go to POS entry
    page.goto(f"{BASE_URL}/pos/entry")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)
    
    ss = screenshot(page, f"pos_entry_{role_name}")
    
    body = page.locator("body").text_content()
    log(f"POS entry page loaded ({len(body)} chars)")
    
    # Check for "Buka Toko" or active session
    if "buka toko" in body.lower():
        log("Found 'Buka Toko' button — no active session")
        # Try opening store
        try:
            page.locator("button:has-text('Buka Toko'), a:has-text('Buka Toko')").first.click()
            page.wait_for_timeout(1000)
            screenshot(page, f"pos_buka_toko_{role_name}")
            
            # Fill opening cash if modal appears
            cash_input = page.locator("input[type='number'], input[name='opening_cash']")
            if cash_input.count() > 0:
                cash_input.first.fill("200000")
                page.locator("button:has-text('Simpan'), button:has-text('Buka')").first.click()
                page.wait_for_load_state("networkidle")
                page.wait_for_timeout(1000)
                log("Opening cash set to 200000")
                screenshot(page, f"pos_opened_{role_name}")
        except Exception as e:
            log(f"Buka Toko flow error: {str(e)[:100]}")
    elif "tutup toko" in body.lower() or "pos" in page.url.lower():
        log("Active session found or on POS page")
    else:
        log(f"POS page content: {body[:200]}")
    
    # Navigate to POS main
    page.goto(f"{BASE_URL}/pos")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)
    screenshot(page, f"pos_main_{role_name}")

def test_navigation_visibility(page, role_name, should_see, should_not_see):
    """Test navigation menu visibility"""
    print(f"\n{'#'*60}")
    print(f"# Testing Navigation Visibility ({role_name})")
    print(f"{'#'*60}")
    
    page.goto(f"{BASE_URL}/dashboard")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)
    
    # Check sidebar/nav
    for item in should_see:
        found = page.locator(f"text='{item}'").count() > 0 or \
                page.locator(f"a:has-text('{item}')").count() > 0
        if found:
            log(f"✓ '{item}' visible (expected)")
        else:
            add_finding("MEDIUM", f"Nav: '{item}' not found",
                "View navigation", f"'{item}' should be visible", "Not found in nav")
    
    for item in should_not_see:
        found = page.locator(f"text='{item}'").count() > 0 or \
                page.locator(f"a:has-text('{item}')").count() > 0
        if found:
            add_finding("MEDIUM", f"Nav: '{item}' should be hidden",
                "View navigation", f"'{item}' should be hidden for {role_name}", "Found in nav")
        else:
            log(f"✓ '{item}' hidden (expected)")
    
    screenshot(page, f"nav_{role_name}")

def main():
    print("=" * 60)
    print("QA EXPLORATORY TEST — SINGLE OUTLET (Chockles)")
    print(f"Started: {datetime.now().isoformat()}")
    print("=" * 60)
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, args=["--no-sandbox"])
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        )
        page = context.new_page()
        
        # Collect console errors
        console_errors = []
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
        
        # ========================================
        # PHASE 1: OWNER TESTING
        # ========================================
        print("\n" + "=" * 60)
        print("PHASE 1: OWNER (owner@chockles.test)")
        print("=" * 60)
        
        if test_login(page, "owner@chockles.test", "password", "Owner"):
            # Navigation check — single outlet
            test_navigation_visibility(page, "Owner",
                should_see=["Dashboard", "Produk", "Penjualan", "Biaya", "Laporan"],
                should_not_see=["Kelola Outlet", "Distribusi"]
            )
            
            # Test each page
            test_dashboard(page, "Owner")
            test_page(page, "/products", "Produk", "Owner")
            test_page(page, "/ingredients", "Bahan Baku", "Owner")
            test_page(page, "/sales", "Penjualan", "Owner")
            test_expense_flow(page, "Owner")
            test_page(page, "/reports/pnl", "Laporan P&L", "Owner")
            test_page(page, "/tax/simulator", "Simulator Pajak", "Owner")
        
        # ========================================
        # PHASE 2: KASIR TESTING
        # ========================================
        print("\n" + "=" * 60)
        print("PHASE 2: KASIR (kasir@chockles.test)")
        print("=" * 60)
        
        # Logout first
        page.goto(f"{BASE_URL}/logout")
        page.wait_for_timeout(1000)
        
        if test_login(page, "kasir@chockles.test", "password", "Kasir"):
            test_pos_flow(page, "Kasir")
        
        # ========================================
        # SUMMARY
        # ========================================
        print("\n" + "=" * 60)
        print("QA SUMMARY")
        print("=" * 60)
        
        critical = [f for f in FINDINGS if f["severity"] == "CRITICAL"]
        high = [f for f in FINDINGS if f["severity"] == "HIGH"]
        medium = [f for f in FINDINGS if f["severity"] == "MEDIUM"]
        low = [f for f in FINDINGS if f["severity"] == "LOW"]
        
        print(f"\nTotal findings: {len(FINDINGS)}")
        print(f"  CRITICAL: {len(critical)}")
        print(f"  HIGH: {len(high)}")
        print(f"  MEDIUM: {len(medium)}")
        print(f"  LOW: {len(low)}")
        
        if FINDINGS:
            print("\nDetailed findings:")
            for i, f in enumerate(FINDINGS, 1):
                print(f"\n--- Finding #{i} [{f['severity']}] ---")
                print(f"  Title: {f['title']}")
                print(f"  Steps: {f['steps']}")
                print(f"  Expected: {f['expected']}")
                print(f"  Actual: {f['actual']}")
        
        if console_errors:
            print(f"\nConsole errors captured: {len(console_errors)}")
            for err in console_errors[:5]:
                print(f"  - {err[:200]}")
        
        # Save report
        report = {
            "timestamp": datetime.now().isoformat(),
            "test_type": "QA Exploratory — Single Outlet",
            "business": "Chockles",
            "total_findings": len(FINDINGS),
            "critical": len(critical),
            "high": len(high),
            "medium": len(medium),
            "low": len(low),
            "findings": FINDINGS,
            "console_errors": console_errors[:10],
        }
        
        report_path = f"{SCREENSHOTS_DIR}/qa_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_path, "w") as f:
            json.dump(report, f, indent=2)
        print(f"\nReport saved: {report_path}")
        
        browser.close()

if __name__ == "__main__":
    main()
