import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('owner confirms an assistant task after reading live records', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.getByRole('link', { name: 'Assistant' }).click();
    await expect(page).toHaveURL(/\/ai/);

    await page.getByLabel('Ask about requests, jobs, or invoices').fill('How many open requests do we have?');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText('There are 1 open service requests.')).toBeVisible();

    await page.getByLabel('Ask about requests, jobs, or invoices').fill('Show overdue invoices.');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText(/There are \d+ overdue invoices totaling SAR 1150\.00/)).toBeVisible();

    await page.getByLabel('Ask about requests, jobs, or invoices').fill('Create a task for the technician.');
    await page.getByRole('button', { name: 'Send' }).click();
    const card = page.getByTestId('ai-confirmation');
    await expect(card).toBeVisible();
    await expect(card.getByText('Follow up for the technician')).toBeVisible();
    await expect(card.getByText('Riyadh Technician')).toBeVisible();
    await card.getByRole('button', { name: 'Confirm' }).click();
    await expect(page.getByText('Follow up for the technician').last()).toBeVisible();

    await page.goto('/tasks');
    await expect(page.getByRole('cell', { name: /Follow up for the technician/ })).toBeVisible();
    await expect(page.getByText('Task created').first()).toBeVisible();
});

test('technician cannot create a task through the assistant', async ({ page }) => {
    await login(page, 'technician@riyadh-cooling.test');
    await page.goto('/ai');
    await page.getByLabel('Ask about requests, jobs, or invoices').fill('Create a task for the technician.');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText('I could not retrieve that from the application.')).toBeVisible();
    await expect(page.getByTestId('ai-confirmation')).toHaveCount(0);
});
