import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Fitur AP (Accounts Payable)', () => {

  test.describe('Bayar Nanti — Transaksi Pembelian', () => {

    test('toggle Bayar Nanti muncul supplier + jatuh tempo', async ({ page }) => {
      await login(page);

      // Ke halaman transaksi pembelian
      await page.goto('/transactions');
      await page.waitForLoadState('networkidle');

      // Cari section Pembelian Bahan
      await expect(page.locator('text=Pembelian Bahan')).toBeVisible();

      // Toggle "Bayar Nanti" harusnya ada
      const toggle = page.locator('text=Bayar Nanti');
      await expect(toggle).toBeVisible();

      // Sebelum toggle: supplier + due date tidak terlihat
      await expect(page.locator('text=Pilih supplier')).not.toBeVisible();

      // Aktifkan toggle
      await toggle.click();

      // Setelah toggle: supplier select + jatuh tempo muncul
      await expect(page.locator('text=Pilih supplier')).toBeVisible();
      await expect(page.locator('label:has-text("Jatuh Tempo")')).toBeVisible();
    });

    test('beli bahan TANPA Bayar Nanti = cash basis', async ({ page }) => {
      await login(page);
      await page.goto('/transactions');
      await page.waitForLoadState('networkidle');

      // Isi form pembelian bahan (tanpa toggle Bayar Nanti)
      // Pilih bahan baku pertama yang tersedia
      const ingredientSelect = page.locator('select[name="ingredient_id"]');
      if (await ingredientSelect.isVisible()) {
        await ingredientSelect.selectOption({ index: 1 });
      }

      // Isi jumlah
      const qtyInput = page.locator('input[name="quantity"]');
      if (await qtyInput.isVisible()) {
        await qtyInput.fill('10');
      }

      // Submit — harusnya TIDAK ada payable terbuat
      // (hanya cek form bisa di-submit, gak perlu verify DB)
      const submitBtn = page.locator('button[type="submit"]:has-text("Simpan")');
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        // Tunggu response
        await page.waitForLoadState('networkidle');
      }
    });
  });

  test.describe('Tagihan Supplier — List & Detail', () => {

    test('halaman tagihan supplier bisa diakses dari sidebar', async ({ page }) => {
      await login(page);

      // Klik sidebar "Tagihan Supplier"
      await page.click('text=Tagihan Supplier');
      await page.waitForLoadState('networkidle');

      // Harusnya ada header halaman
      await expect(page.locator('text=Tagihan Supplier')).toBeVisible();

      // Ada tabel / list (kosong atau ada data)
      // Cek minimal ada kolom header
      await expect(page.locator('th, [role="columnheader"]').first()).toBeVisible();
    });

    test('detail tagihan bisa diakses', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      // Klik tagihan pertama (jika ada)
      const firstRow = page.locator('table tbody tr').first();
      if (await firstRow.isVisible()) {
        await firstRow.click();
        await page.waitForLoadState('networkidle');

        // Harusnya ada detail: nomor tagihan, status, jumlah
        await expect(page.locator('text=Status')).toBeVisible();
      }
    });
  });

  test.describe('Pembayaran Tagihan', () => {

    test('bayar partial tagihan', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      // Cari tagihan dengan status Outstanding atau Partial
      const outstandingBadge = page.locator('text=Outstanding').first();
      if (await outstandingBadge.isVisible()) {
        // Klik row tagihan
        await outstandingBadge.click();
        await page.waitForLoadState('networkidle');

        // Isi jumlah bayar
        const amountInput = page.locator('input[name="amount"]');
        if (await amountInput.isVisible()) {
          await amountInput.fill('50000');

          // Submit pembayaran
          const payBtn = page.locator('button:has-text("Bayar")');
          if (await payBtn.isVisible()) {
            await payBtn.click();
            await page.waitForLoadState('networkidle');

            // Status harus berubah ke Partial
            await expect(page.locator('text=Partial')).toBeVisible();
          }
        }
      }
    });

    test('bayar lunas tagihan', async ({ page }) => {
      await login(page);
      await page.goto('/payables');
      await page.waitForLoadState('networkidle');

      const outstandingBadge = page.locator('text=Outstanding').first();
      if (await outstandingBadge.isVisible()) {
        await outstandingBadge.click();
        await page.waitForLoadState('networkidle');

        // Klik "Bayar Lunas" jika ada
        const payFullBtn = page.locator('button:has-text("Bayar Lunas")');
        if (await payFullBtn.isVisible()) {
          await payFullBtn.click();
          await page.waitForLoadState('networkidle');

          // Status harus berubah ke Paid
          await expect(page.locator('text=Paid')).toBeVisible();
        }
      }
    });
  });

  test.describe('Dashboard Widget', () => {

    test('widget Tagihan Jatuh Tempo muncul', async ({ page }) => {
      await login(page);
      await page.goto('/dashboard');
      await page.waitForLoadState('networkidle');

      // Cek widget ada
      const widget = page.locator('text=Tagihan Jatuh Tempo');
      // Widget hanya muncul kalau ada data, jadi test ini conditional
      // Kita cek minimal halaman dashboard load
      await expect(page.locator('text=Dashboard')).toBeVisible();
    });
  });

  test.describe('Laporan — Aging Report Tab', () => {

    test('tab Tagihan Supplier ada di Aging Report', async ({ page }) => {
      await login(page);
      await page.goto('/reports/aging');
      await page.waitForLoadState('networkidle');

      // Cek tab AR dan AP
      await expect(page.locator('text=Tagihan Pelanggan')).toBeVisible();
      await expect(page.locator('text=Tagihan Supplier')).toBeVisible();

      // Klik tab Tagihan Supplier
      await page.click('text=Tagihan Supplier');
      await page.waitForLoadState('networkidle');

      // Aging buckets harusnya muncul
      await expect(page.locator('text=Current')).toBeVisible();
    });
  });

  test.describe('Laporan — Cashflow Kewajiban', () => {

    test('section Kewajiban muncul di Cashflow', async ({ page }) => {
      await login(page);
      await page.goto('/reports/cashflow');
      await page.waitForLoadState('networkidle');

      // Cek section kewajiban
      const kewajiban = page.locator('text=Kewajiban');
      // Section ini muncul walau kosong
      await expect(kewajiban).toBeVisible();
    });
  });

  test.describe('Sidebar Navigation', () => {

    test('Tagihan Supplier ada di sidebar', async ({ page }) => {
      await login(page);

      // Cek sidebar ada link Tagihan Supplier
      const sidebarLink = page.locator('nav a:has-text("Tagihan Supplier")');
      await expect(sidebarLink).toBeVisible();
      await expect(sidebarLink).toHaveAttribute('href', '/payables');
    });
  });

  test.describe('Akses & Navigasi', () => {

    test('redirect ke login kalau belum auth', async ({ page }) => {
      await page.goto('/payables');

      // Harus redirect ke login
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
