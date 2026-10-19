import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('opens the dispatch board in both directions', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.getByRole('link', { name: 'Dispatch', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Dispatch' })).toBeVisible();
    await expect(page.getByTestId('dispatch-board')).toBeVisible();
    await page.screenshot({ path: 'test-results/dispatch-ltr-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Dispatch' })).toBeVisible();
    await page.screenshot({ path: 'test-results/dispatch-ltr-mobile.png', fullPage: true });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.getByRole('heading', { name: 'التوزيع' })).toBeVisible();
    await page.screenshot({ path: 'test-results/dispatch-rtl-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await expect(page.getByRole('heading', { name: 'التوزيع' })).toBeVisible();
    await page.screenshot({ path: 'test-results/dispatch-rtl-mobile.png', fullPage: true });
});
