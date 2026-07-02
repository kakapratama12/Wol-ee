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
  // Tunggu React app selesai render — form email harus muncul
  await page.waitForSelector('input[name="email"]', { timeout: 15000 });
  await page.waitForSelector('input[name="password"]', { timeout: 5000 });

  await page.fill('input[name="email"]', email || TEST_EMAIL);
  await page.fill('input[name="password"]', password || TEST_PASSWORD);

  // Klik tombol "Log in" — button text, bukan type="submit"
  await page.getByRole('button', { name: 'Log in' }).click();

  // Tunggu redirect ke dashboard
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  await page.waitForLoadState('networkidle');
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
