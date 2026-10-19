import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('owner sees a whatsapp request after the customer confirms', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.getByRole('link', { name: 'WhatsApp' }).click();
    await expect(page).toHaveURL(/\/whatsapp$/);

    await page.getByLabel('Phone').fill('0550001001');
    await page.getByLabel('Message').fill('The air conditioner is not cooling.');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await page.getByRole('link', { name: 'North Wind Villa' }).click();
    await expect(page.getByText('Please confirm this action before I save it.')).toBeVisible();

    await page.getByLabel('Simulate inbound message').fill('yes');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await expect(page.getByText('Done. Air conditioner is not cooling was saved.')).toBeVisible();

    await page.goto('/requests');
    await expect(page.getByText('Air conditioner is not cooling')).toBeVisible();
});

test('customer books an open slot and cannot book the same slot again', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.goto('/whatsapp');
    await page.getByLabel('Phone').fill('0550001001');
    await page.getByLabel('Message').fill('I need a technician tomorrow at 4.');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await page.getByRole('link', { name: 'North Wind Villa' }).click();
    await expect(page.getByText('Please confirm this action before I save it.').last()).toBeVisible();

    await page.getByLabel('Simulate inbound message').fill('yes');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await expect(page.getByText('Done. Appointment was saved.')).toBeVisible();

    await page.getByLabel('Simulate inbound message').fill('I need a technician tomorrow at 4.');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await expect(page.getByText('That time is not available.')).toBeVisible();
});

test('customer cannot read company revenue', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.goto('/whatsapp');
    await page.getByLabel('Phone').fill('0550001002');
    await page.getByLabel('Message').fill('How much revenue did the company make?');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await page.getByRole('link', { name: 'Al Noor Trading Demo' }).click();
    await expect(page.getByText('I can only help with your own service request or appointment.')).toBeVisible();
    await expect(page.getByText('SAR')).toHaveCount(0);
});

test('customer request for a person stops the assistant', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    await page.goto('/whatsapp');
    await page.getByLabel('Phone').fill('0550001002');
    await page.getByLabel('Message').fill('I want to talk to a person.');
    await page.getByRole('button', { name: 'Simulate inbound message' }).click();
    await page.getByRole('link', { name: 'Al Noor Trading Demo' }).click();
    await expect(page.getByText('Waiting for a teammate').first()).toBeVisible();
    await expect(page.getByText('A teammate will continue this conversation.')).toBeVisible();
});
