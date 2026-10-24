import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('appointment times stay in the organization timezone through create, list and edit', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');

    await page.goto('/appointments/create');
    await page.getByRole('combobox', { name: 'Customer' }).selectOption({ label: 'North Wind Villa' });
    await page.getByRole('combobox', { name: 'Technician' }).selectOption({ label: 'Riyadh Technician' });
    await page.getByLabel('Start').fill('2027-01-11T09:00');
    await page.getByLabel('End').fill('2027-01-11T10:30');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page).toHaveURL(/\/appointments$/);

    await page.goto('/appointments?date=2027-01-11');
    const row = page.getByRole('row').filter({ hasText: 'North Wind Villa' }).filter({ hasText: '2027' });
    await expect(row).toContainText('09:00');
    await expect(row).toContainText('10:30');

    await row.getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByLabel('Start')).toHaveValue('2027-01-11T09:00');
    await expect(page.getByLabel('End')).toHaveValue('2027-01-11T10:30');
});
