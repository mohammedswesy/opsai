import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('task due time stays in the organization timezone through create, edit and save', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');

    await page.goto('/tasks/create');
    await page.getByLabel('Title').fill('Timezone round trip');
    await page.getByLabel('Due').fill('2026-12-15T09:00');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page).toHaveURL(/\/tasks$/);

    const row = page.getByRole('row').filter({ hasText: 'Timezone round trip' });
    await expect(row).toContainText('09:00');

    await row.getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByLabel('Due')).toHaveValue('2026-12-15T09:00');

    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page).toHaveURL(/\/tasks$/);
    await expect(page.getByRole('row').filter({ hasText: 'Timezone round trip' })).toContainText('09:00');

    await page.getByRole('row').filter({ hasText: 'Timezone round trip' }).getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByLabel('Due')).toHaveValue('2026-12-15T09:00');
});
