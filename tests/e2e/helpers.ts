import { expect, type Page } from '@playwright/test';

export async function login(page: Page, email: string, password = 'password'): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/$/);

    const english = page.getByRole('button', { name: 'English' });
    if (await english.count()) {
        await english.click();
    }

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}

export async function kpiValue(page: Page, label: string): Promise<number> {
    const card = page.locator('article').filter({ hasText: label });
    const value = await card.locator('p').nth(1).innerText();

    return Number(value);
}

export async function rowLink(page: Page, path: string, rowText: string, linkName: string): Promise<string> {
    await page.goto(path);
    const href = await page.getByRole('row').filter({ hasText: rowText }).getByRole('link', { name: linkName }).first().getAttribute('href');
    expect(href).toBeTruthy();

    return href ?? '';
}
