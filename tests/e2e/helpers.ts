import { test as base, expect, type Page } from '@playwright/test';

// Test credentials — isi dengan akun test di staging
const TEST_EMAIL = process.env.TEST_EMAIL || 'admin@wol-ee.test';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'password';

/**
 * Login ke Wol-ee via form.
 * Dipanggil di awal setiap test yang butuh auth.
 */
export async function login(page: Page, email?: string, password?: string) {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');

  await page.fill('input[name="email"]', email || TEST_EMAIL);
  await page.fill('input[name="password"]', password || TEST_PASSWORD);
  await page.click('button[type="submit"]');

  // Tunggu redirect ke dashboard
  await page.waitForURL('**/dashboard', { timeout: 10000 });
  await expect(page.locator('text=Dashboard')).toBeVisible();
}

/**
 * Helper: format rupiah for assertions
 */
export function rupiah(amount: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}
