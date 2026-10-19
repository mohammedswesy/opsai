import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('captures the redesigned dashboard', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page, 'owner@riyadh-cooling.test');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.screenshot({ path: 'test-results/step1-dashboard-ltr-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await page.screenshot({ path: 'test-results/step1-dashboard-ltr-mobile.png', fullPage: true });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText(/صباح|طاب|مساء/);
    await page.screenshot({ path: 'test-results/step1-dashboard-rtl-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await page.screenshot({ path: 'test-results/step1-dashboard-rtl-mobile.png', fullPage: true });
});
