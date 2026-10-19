import { expect, test } from '@playwright/test';
import { kpiValue, login, rowLink } from './helpers';

test('owner completes a job from the screens', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');
    const completedBefore = await kpiValue(page, 'Completed work orders');

    await page.getByRole('link', { name: 'Customers' }).click();
    await expect(page).toHaveURL(/\/customers$/);
    await page.getByRole('link', { name: 'Create' }).click();
    await page.getByLabel('Customer').fill('Playwright Villa');
    await page.getByRole('textbox', { name: 'Phone', exact: true }).fill('0550001777');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('heading', { name: 'Playwright Villa' })).toBeVisible();

    await page.getByRole('link', { name: 'Services' }).click();
    await expect(page).toHaveURL(/\/services$/);
    await page.getByRole('link', { name: 'Create' }).click();
    await page.getByLabel('Service').fill('Night Filter Service');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('cell', { name: 'Night Filter Service' })).toBeVisible();

    await page.getByRole('link', { name: 'Technicians' }).click();
    await expect(page.getByRole('cell', { name: 'Riyadh Technician' })).toBeVisible();

    await page.getByRole('link', { name: 'Requests' }).click();
    await expect(page).toHaveURL(/\/requests$/);
    await page.getByRole('link', { name: 'Create' }).click();
    await expect(page).toHaveURL(/\/requests\/create$/);
    await page.getByRole('combobox', { name: 'Customer' }).selectOption({ label: 'Playwright Villa' });
    await page.getByRole('combobox', { name: 'Service', exact: true }).selectOption({ label: 'Night Filter Service' });
    await page.getByLabel('Subject').fill('Night filter is clogged');
    await page.getByLabel('Description').fill('The return vent filter is blocked.');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('heading', { name: 'Night filter is clogged' })).toBeVisible();

    await page.getByRole('link', { name: 'Appointments' }).click();
    await expect(page).toHaveURL(/\/appointments$/);
    await page.getByRole('link', { name: 'Create' }).click();
    await expect(page).toHaveURL(/\/appointments\/create$/);
    await page.getByRole('combobox', { name: 'Customer' }).selectOption({ label: 'Playwright Villa' });
    await page.getByRole('combobox', { name: 'Technician' }).selectOption({ label: 'Riyadh Technician' });
    await page.getByRole('combobox', { name: 'Service request', exact: true }).selectOption({ label: 'Night filter is clogged' });
    await page.getByLabel('Start').fill('2026-12-15T09:00');
    await page.getByLabel('End').fill('2026-12-15T10:00');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('cell', { name: 'Playwright Villa' })).toBeVisible();

    await page.getByRole('link', { name: 'Requests' }).click();
    await page.getByRole('row').filter({ hasText: 'Night filter is clogged' }).getByRole('link', { name: 'View' }).click();
    await expect(page.getByText('Assigned technician: Riyadh Technician')).toBeVisible();

    await page.getByRole('link', { name: 'Create' }).click();
    await expect(page).toHaveURL(/\/work-orders\/create/);
    await expect(page.getByRole('combobox', { name: 'Customer' })).toHaveValue(/.+/);
    await page.getByRole('combobox', { name: 'Technician' }).selectOption({ label: 'Riyadh Technician' });
    await page.getByLabel('Title').fill('Replace the night filter');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('heading', { name: 'Replace the night filter' })).toBeVisible();

    const statusLabels: Record<string, string> = {
        accepted: 'Accepted',
        on_the_way: 'On the way',
        arrived: 'Arrived',
        in_progress: 'In progress',
        completed: 'Completed',
    };

    for (const status of Object.keys(statusLabels)) {
        await page.getByLabel('Status').selectOption(status);
        await page.getByRole('button', { name: 'Save' }).click();
        if (status === 'completed') {
            await page.getByRole('button', { name: 'Confirm' }).click();
        }
        await expect(page.getByText(statusLabels[status]).first()).toBeVisible();
    }

    await expect(page.getByText('Completed').first()).toBeVisible();
    await page.goto('/');
    expect(await kpiValue(page, 'Completed work orders')).toBe(completedBefore + 1);
});

test('riyadh cannot open jeddah records', async ({ page }) => {
    await login(page, 'owner@jeddah-maintenance.test');
    const urls = [
        await rowLink(page, '/customers', 'Harbor Office Demo', 'View'),
        await rowLink(page, '/services', 'Harbor Coil Check', 'Edit'),
        await rowLink(page, '/technicians', 'Jeddah Owner', 'View'),
        await rowLink(page, '/requests', 'Office thermostat check', 'View'),
        await rowLink(page, '/appointments', 'Harbor Office Demo', 'Edit'),
        await rowLink(page, '/work-orders', 'Harbor thermostat visit', 'View'),
        await rowLink(page, '/invoices', 'Harbor Office Demo', 'View'),
    ];

    await page.getByRole('button', { name: 'Account' }).click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await login(page, 'owner@riyadh-cooling.test');

    for (const path of ['/customers', '/services', '/technicians', '/requests', '/appointments', '/work-orders', '/invoices']) {
        await page.goto(path);
        await expect(page.getByText('Harbor Office Demo')).toHaveCount(0);
        await expect(page.getByText('Harbor Coil Check')).toHaveCount(0);
        await expect(page.getByText('Office thermostat check')).toHaveCount(0);
        await expect(page.getByText('Harbor thermostat visit')).toHaveCount(0);
    }

    await page.goto('/technicians');
    await expect(page.getByText('Jeddah Owner')).toHaveCount(0);
    await expect(page.getByText('Riyadh Technician')).toBeVisible();

    for (const url of urls) {
        const response = await page.goto(url);
        expect(response?.status()).toBe(404);
    }
});

test('technician workflow stays inside the technician role', async ({ page }) => {
    await login(page, 'technician@riyadh-cooling.test');

    await expect(page.getByRole('link', { name: 'Work orders' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Team' })).toHaveCount(0);

    await page.getByRole('link', { name: 'Customers' }).click();
    await expect(page.getByRole('link', { name: 'Create' })).toHaveCount(0);

    const createPage = await page.goto('/customers/create');
    expect(createPage?.status()).toBe(403);

    const teamPage = await page.goto('/users');
    expect(teamPage?.status()).toBe(403);

    await page.goto('/work-orders');
    await page.getByRole('row').filter({ hasText: 'Inspect villa split unit' }).getByRole('link', { name: 'View' }).click();
    await expect(page.getByRole('heading', { name: 'Update status' })).toBeVisible();

    const cookies = await page.context().cookies();
    const token = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    const forbidden = await page.request.post('/customers', {
        form: {
            type: 'individual',
            name: 'Sneaky Customer',
            phone: '0550001888',
            status: 'active',
        },
        headers: {
            'X-XSRF-TOKEN': decodeURIComponent(token?.value ?? ''),
        },
    });
    expect(forbidden.status()).toBe(403);
});
