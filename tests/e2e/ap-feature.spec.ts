import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Fitur AP (Accounts Payable)', () => {

  test.describe('Tagihan Supplier — Navigasi', () => {

    test('Tagihan Supplier bisa diakses dari sidebar Partner', async ({ page }) => {
      await login(page);

      // "Tagihan Supplier" ada di group Partner
      await page.click('button:has-text("Partner")');
      await page.waitForTimeout(500);

      await page.click('a:has-text("Tagihan Supplier")');
      await page.waitForLoadState('networkidle');

      await expect(page.getByRole('heading', { name: 'Tagihan Supplier' })).toBeVisible();
    });
  });

  test.describe('Tagihan Supplier — List & Detail', () => {

    test('halaman tagihan supplier bisa diakses langsung', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      await expect(page.getByRole('heading', { name: 'Tagihan Supplier' })).toBeVisible();
    });

    test('detail tagihan bisa diakses', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      const firstRow = page.locator('table tbody tr').first();
      if (await firstRow.isVisible()) {
        await firstRow.click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('text=Status')).toBeVisible();
      }
    });
  });

  test.describe('Pembayaran Tagihan', () => {

    test('bayar partial tagihan', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      const outstandingBadge = page.locator('text=Outstanding').first();
      if (await outstandingBadge.isVisible()) {
        await outstandingBadge.click();
        await page.waitForLoadState('networkidle');

        const amountInput = page.locator('input[name="amount"]');
        if (await amountInput.isVisible()) {
          await amountInput.fill('50000');

          const payBtn = page.locator('button:has-text("Bayar")');
          if (await payBtn.isVisible()) {
            await payBtn.click();
            await page.waitForLoadState('networkidle');
          }
        }
      }
    });
  });

  test.describe('Dashboard Widget', () => {

    test('dashboard bisa diakses', async ({ page }) => {
      await login(page);
      await page.goto('/dashboard');
      await page.waitForLoadState('networkidle');
      await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });
  });

  test.describe('Laporan — Aging Report Tab', () => {

    test('tab Piutang dan Tagihan Supplier ada di Aging Report', async ({ page }) => {
      await login(page);
      await page.goto('/reports/aging');
      await page.waitForLoadState('networkidle');

      // Tab text: "Piutang (AR)" dan "Tagihan Supplier (AP)"
      await expect(page.locator('button:has-text("Piutang (AR)")')).toBeVisible();
      await expect(page.locator('button:has-text("Tagihan Supplier (AP)")')).toBeVisible();

      // Klik tab Tagihan Supplier
      await page.click('button:has-text("Tagihan Supplier (AP)")');
      await page.waitForLoadState('networkidle');
    });
  });

  test.describe('Laporan — Cashflow Kewajiban', () => {

    test('section Kewajiban muncul di Cashflow', async ({ page }) => {
      await login(page);
      await page.goto('/reports/cashflow');
      await page.waitForLoadState('networkidle');
      await expect(page.locator('text=Kewajiban')).toBeVisible();
    });
  });

  test.describe('Bayar Nanti — Transaksi Pembelian', () => {

    test('toggle Bayar Nanti muncul supplier + jatuh tempo', async ({ page }) => {
      await login(page);
      await page.goto('/transactions');
      await page.waitForLoadState('networkidle');

      const toggle = page.locator('text=Bayar Nanti');
      await expect(toggle).toBeVisible();

      await toggle.click();

      // Supplier select muncul
      await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
      // Due date field muncul
      await expect(page.getByText('Jatuh Tempo', { exact: true })).toBeVisible();
    });
  });

  test.describe('Akses & Navigasi', () => {

    test('redirect ke login kalau belum auth', async ({ page }) => {
      await page.goto('/payables');
      await page.waitForURL('**/login', { timeout: 5000 });
      await expect(page.locator('text=Log in')).toBeVisible();
    });

    test('semua halaman AP bisa diakses tanpa error 500', async ({ page }) => {
      await login(page);

      const pages = ['/payables', '/reports/aging', '/reports/cashflow'];

      for (const path of pages) {
        const response = await page.goto(path);
        expect(response?.status()).toBeLessThan(500);
      }
    });
  });
});
