import { readFileSync } from 'node:fs';
import { expect, type Page, test } from '@playwright/test';
import { login } from './helpers';

const legal = {
    name: 'شركة تبريد الرياض للصيانة',
    taxNumber: '310123456700003',
    address: 'طريق الملك فهد، حي العليا',
    city: 'الرياض',
};

async function openFirstInvoice(page: Page, viewLabel: string) {
    await page.goto('/invoices');
    await page.getByRole('row').nth(1).getByRole('link', { name: viewLabel }).click();
    await expect(page.getByRole('heading', { level: 1, name: /INV-/ })).toBeVisible();
}

async function downloadPdf(page: Page, linkName: string, savePath: string) {
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('link', { name: linkName }).click()]);
    expect(download.suggestedFilename()).toMatch(/^INV-[A-Za-z0-9-]+\.pdf$/);
    await download.saveAs(savePath);
    expect(readFileSync(savePath).subarray(0, 5).toString()).toBe('%PDF-');
}

test('owner saves legal details, sees them on the invoice and downloads the PDF in both languages', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');

    await page.goto('/organization');
    await page.getByLabel('Tax number').fill('123');
    await page.getByRole('button', { name: 'Save organization' }).click();
    await expect(page.getByText('The tax number must be 15 digits that start and end with 3.')).toBeVisible();

    await page.getByLabel('Legal name').fill(legal.name);
    await page.getByLabel('Tax number').fill(legal.taxNumber);
    await page.getByLabel('Address').fill(legal.address);
    await page.getByLabel('City').fill(legal.city);
    await page.getByRole('button', { name: 'Save organization' }).click();
    await expect(page.getByText('Organization updated.')).toBeVisible();
    // Saving switches the session to the organization's locale (Arabic for this seed); come back to English.
    if ((await page.locator('html').getAttribute('lang')) === 'ar') {
        await page.getByRole('button', { name: 'English' }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    }
    await expect(page.getByLabel('Tax number')).toHaveValue(legal.taxNumber);
    await page.screenshot({ path: 'test-results/legal-settings-en.png' });

    await openFirstInvoice(page, 'View');
    await expect(page.getByTestId('invoice-tax-number')).toContainText(legal.taxNumber);
    await expect(page.getByTestId('invoice-seller-address')).toContainText(legal.city);
    await expect(page.getByText(legal.name).first()).toBeVisible();
    await page.screenshot({ path: 'test-results/legal-invoice-en.png' });
    await downloadPdf(page, 'Download PDF', 'test-results/invoice-en.pdf');

    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await page.goto('/organization');
    await expect(page.getByLabel('الرقم الضريبي')).toHaveValue(legal.taxNumber);
    await page.screenshot({ path: 'test-results/legal-settings-ar.png' });

    await openFirstInvoice(page, 'عرض');
    await expect(page.getByTestId('invoice-tax-number')).toContainText(legal.taxNumber);
    await page.screenshot({ path: 'test-results/legal-invoice-ar.png' });
    await downloadPdf(page, 'تنزيل PDF', 'test-results/invoice-ar.pdf');

    await page.getByRole('button', { name: 'English' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});
