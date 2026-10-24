import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('owner invoices a completed work order and records both payments', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    const paymentsBefore = await page.locator('article').filter({ hasText: "Today's payments" }).locator('p').nth(1).innerText();

    await page.goto('/work-orders');
    await page.getByRole('row').filter({ hasText: 'Seeded completed visit' }).getByRole('link', { name: 'View' }).click();
    await page.getByRole('button', { name: 'Generate invoice' }).click();
    await expect(page.getByRole('heading', { name: /INV-\d{4}-\d{6}/ })).toBeVisible();
    await expect(page.getByText(/1,?150\.00/).first()).toBeVisible();

    await page.getByRole('button', { name: 'Issue' }).click();
    await expect(page.getByText('Issued', { exact: true }).first()).toBeVisible();

    await page.getByRole('link', { name: 'Record payment' }).click();
    await page.getByLabel('Amount').fill('500');
    await page.getByRole('button', { name: 'Save' }).click();
    await page.getByRole('link', { name: /INV-/ }).click();
    await expect(page.getByText('Partially paid', { exact: true }).first()).toBeVisible();

    await page.getByRole('link', { name: 'Record payment' }).click();
    await page.getByLabel('Amount').fill('650');
    await page.getByRole('button', { name: 'Save' }).click();
    await page.getByRole('link', { name: /INV-/ }).click();
    await expect(page.getByText('Paid', { exact: true }).first()).toBeVisible();

    await page.goto('/');
    const paymentsAfter = await page.locator('article').filter({ hasText: "Today's payments" }).locator('p').nth(1).innerText();
    const amount = (value: string) => value.replace(/[^\d.]/g, '');
    expect(amount(paymentsBefore)).toBe('0.00');
    expect(amount(paymentsAfter)).toBe('1150.00');
});

test('technician cannot open or create invoices', async ({ page }) => {
    await login(page, 'technician@riyadh-cooling.test');

    await expect(page.getByRole('link', { name: 'Invoices' })).toHaveCount(0);
    const pageResponse = await page.goto('/invoices/create');
    expect(pageResponse?.status()).toBe(403);

    const cookies = await page.context().cookies();
    const token = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    const forbidden = await page.request.post('/invoices', {
        form: {
            customer_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            discount: '0',
            'items[0][description]': 'Blocked',
            'items[0][quantity]': '1',
            'items[0][unit_price]': '10',
        },
        headers: {
            'X-XSRF-TOKEN': decodeURIComponent(token?.value ?? ''),
        },
    });
    expect(forbidden.status()).toBe(403);
});
