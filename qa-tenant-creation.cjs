/**
 * QA Script: Tenant Creation Flow (Platform Admin)
 * Tests: business_type selector, outlet creation, staff creation
 */
const { chromium } = require('playwright');

const BASE = 'https://staging.wolee.my.id';
const ADMIN = { email: 'admin@wolee.test', password: 'password' };

(async () => {
    const browser = await chromium.launch({ headless: true });
    const results = [];
    let pg;

    const log = (test, status, note = '') => {
        results.push({ test, status, note });
        const icon = status === 'PASS' ? '✅' : status === 'FAIL' ? '❌' : '⚠️';
        console.log(`${icon} ${test}${note ? ' — ' + note : ''}`);
    };

    try {
        // Login as super admin
        const ctx = await browser.newContext();
        pg = await ctx.newPage();
        await pg.goto(`${BASE}/login`);
        await pg.locator('input[type="email"], input[name="email"]').fill(ADMIN.email);
        await pg.locator('input[type="password"], input[name="password"]').fill(ADMIN.password);
        // Login button in Breeze has no type="submit" — it's just a button with text
        await pg.locator('button').filter({ hasText: /log in/i }).click();
        await pg.waitForURL('**/platform**', { timeout: 10000 });
        log('Login as super admin', 'PASS');

        // Navigate to tenants page
        await pg.goto(`${BASE}/platform/tenants`);
        await pg.waitForLoadState('networkidle');
        log('Navigate to /platform/tenants', 'PASS');

        // Click "Tambah Usaha"
        await pg.locator('button').filter({ hasText: /Tambah Usaha/i }).click();
        await pg.waitForTimeout(500);

        // Test 1: Form loads with business_type selector
        const businessTypeSelect = pg.locator('#add-business-type');
        const hasBusinessType = await businessTypeSelect.count() > 0;
        log('T1: Business type selector exists', hasBusinessType ? 'PASS' : 'FAIL');

        // Test 2: Default is single
        const defaultType = await businessTypeSelect.inputValue();
        log('T2: Default business type = single', defaultType === 'single' ? 'PASS' : 'FAIL', defaultType);

        // Test 3: Multi shows outlet checkbox
        await businessTypeSelect.selectOption('multi');
        await pg.waitForTimeout(300);
        const outletCheckbox = pg.locator('#create_outlet');
        const hasOutletCheckbox = await outletCheckbox.count() > 0;
        log('T3: Multi shows outlet checkbox', hasOutletCheckbox ? 'PASS' : 'FAIL');

        // Test 4: Single hides outlet checkbox
        await businessTypeSelect.selectOption('single');
        await pg.waitForTimeout(300);
        const outletHidden = (await pg.locator('#create_outlet').count()) === 0;
        log('T4: Single hides outlet checkbox', outletHidden ? 'PASS' : 'FAIL');

        // Test 5: Check outlet → shows outlet + staff fields
        await businessTypeSelect.selectOption('multi');
        await pg.waitForTimeout(300);
        await outletCheckbox.check();
        await pg.waitForTimeout(300);
        const outletName = pg.locator('#add-outlet-name');
        const staffName = pg.locator('#add-staff-name');
        const hasOutletFields = (await outletName.count()) > 0 && (await staffName.count()) > 0;
        log('T5: Check outlet → shows outlet + staff fields', hasOutletFields ? 'PASS' : 'FAIL');

        // Test 6: Uncheck outlet → hides fields
        await outletCheckbox.uncheck();
        await pg.waitForTimeout(300);
        const fieldsHidden = (await pg.locator('#add-outlet-name').count()) === 0;
        log('T6: Uncheck outlet → hides fields', fieldsHidden ? 'PASS' : 'FAIL');

        // Test 7: Submit single tenant
        await businessTypeSelect.selectOption('single');
        const ts = Date.now();
        await pg.locator('#add-name').fill(`QA Single ${ts}`);
        await pg.locator('#add-pengelola-name').fill('Owner Single QA');
        await pg.locator('#add-pengelola-email').fill(`single${ts}@qa.test`);
        await pg.locator('#add-pengelola-password').fill('password');
        await pg.locator('#add-pengelola-password-confirm').fill('password');
        await pg.locator('button').filter({ hasText: /^Simpan$/ }).click();
        await pg.waitForTimeout(2000);
        const successSingle = await pg.locator('text=Usaha berhasil dibuat').count() > 0;
        log('T7: Submit single tenant', successSingle ? 'PASS' : 'FAIL');

        // Test 8: Submit multi tenant (no outlet)
        await pg.locator('button').filter({ hasText: /Tambah Usaha/i }).click();
        await pg.waitForTimeout(500);
        await pg.locator('#add-business-type').selectOption('multi');
        await pg.locator('#add-name').fill(`QA Multi ${ts}`);
        await pg.locator('#add-pengelola-name').fill('Owner Multi QA');
        await pg.locator('#add-pengelola-email').fill(`multi${ts}@qa.test`);
        await pg.locator('#add-pengelola-password').fill('password');
        await pg.locator('#add-pengelola-password-confirm').fill('password');
        await pg.locator('button').filter({ hasText: /^Simpan$/ }).click();
        await pg.waitForTimeout(2000);
        const successMulti = await pg.locator('text=Usaha berhasil dibuat').count() > 0;
        log('T8: Submit multi tenant (no outlet)', successMulti ? 'PASS' : 'FAIL');

        // Test 9: Submit multi tenant (with outlet + staff)
        await pg.locator('button').filter({ hasText: /Tambah Usaha/i }).click();
        await pg.waitForTimeout(500);
        await pg.locator('#add-business-type').selectOption('multi');
        await pg.waitForTimeout(300);
        await pg.locator('#create_outlet').check();
        await pg.waitForTimeout(300);
        await pg.locator('#add-name').fill(`QA Multi Outlet ${ts}`);
        await pg.locator('#add-pengelola-name').fill('Owner Multi Outlet QA');
        await pg.locator('#add-pengelola-email').fill(`multioutlet${ts}@qa.test`);
        await pg.locator('#add-pengelola-password').fill('password');
        await pg.locator('#add-pengelola-password-confirm').fill('password');
        await pg.locator('#add-outlet-name').fill('Outlet QA Test');
        await pg.locator('#add-outlet-address').fill('Jl. QA No. 1');
        await pg.locator('#add-staff-name').fill('Staff QA');
        await pg.locator('#add-staff-email').fill(`staff${ts}@qa.test`);
        await pg.locator('#add-staff-password').fill('password');
        await pg.locator('#add-staff-password-confirm').fill('password');
        await pg.locator('button').filter({ hasText: /^Simpan$/ }).click();
        await pg.waitForTimeout(2000);
        const successMultiOutlet = await pg.locator('text=Usaha berhasil dibuat').count() > 0;
        log('T9: Submit multi tenant (with outlet + staff)', successMultiOutlet ? 'PASS' : 'FAIL');

        // Summary
        const passed = results.filter(r => r.status === 'PASS').length;
        const failed = results.filter(r => r.status === 'FAIL').length;
        console.log(`\n--- Summary: ${passed}/${results.length} PASS, ${failed} FAIL ---`);

    } catch (e) {
        console.error('Script error:', e.message);
        log('Script execution', 'FAIL', e.message);
    } finally {
        await browser.close();
    }
})();
