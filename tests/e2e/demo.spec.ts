import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers';

const shots: { path: string; heading: string; arabic: string; name: string }[] = [
    { path: '/', heading: 'Dashboard', arabic: 'لوحة المعلومات', name: 'dashboard' },
    { path: '/dispatch', heading: 'Dispatch', arabic: 'التوزيع', name: 'dispatch' },
    { path: '/ai', heading: 'Assistant', arabic: 'المساعد', name: 'ai' },
    { path: '/whatsapp', heading: 'WhatsApp', arabic: 'واتساب', name: 'whatsapp' },
];

async function shot(page: Page, name: string): Promise<void> {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.screenshot({ path: `test-results/${name}-ltr-desktop.png`, fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await page.screenshot({ path: `test-results/${name}-ltr-mobile.png`, fullPage: true });
}

test('captures the demo screens', async ({ page }) => {
    test.setTimeout(180_000);
    await login(page, 'owner@riyadh-cooling.test');

    for (const item of shots) {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(item.path);
        await expect(page.getByRole('heading', item.name === 'dashboard' ? { level: 1 } : { name: item.heading }).first()).toBeVisible();
        await shot(page, item.name);
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/invoices');
    const view = page.getByRole('link', { name: 'View' }).first();
    if (await view.count()) {
        await view.click();
        await expect(page).toHaveURL(/\/invoices\//);
        await shot(page, 'invoice');
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    for (const item of shots) {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(item.path);
        await expect(page.getByRole('heading', item.name === 'dashboard' ? { level: 1 } : { name: item.arabic }).first()).toBeVisible();
        await page.screenshot({ path: `test-results/${item.name}-rtl-desktop.png`, fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await page.screenshot({ path: `test-results/${item.name}-rtl-mobile.png`, fullPage: true });
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/invoices');
    const arabicView = page.getByRole('link', { name: 'عرض' }).first();
    if (await arabicView.count()) {
        await arabicView.click();
        await page.screenshot({ path: 'test-results/invoice-rtl-desktop.png', fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await page.screenshot({ path: 'test-results/invoice-rtl-mobile.png', fullPage: true });
    }
});
