import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers';

const shots = 'test-results/whatsapp-templates';

/** Creates the closed-window conversation in database/e2e.sqlite (the fixture refuses any other database). */
function closedWindowConversation(): string {
    return execFileSync('php', ['tests/e2e/fixtures/whatsapp-closed-window.php'], {
        env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('database/e2e.sqlite'), DB_URL: '', WHATSAPP_PROVIDER: 'fake' },
        encoding: 'utf8',
    }).trim();
}

async function useLocale(page: Page, locale: 'en' | 'ar'): Promise<void> {
    await page.getByRole('button', { name: locale === 'ar' ? 'Arabic' : 'English' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
}

test.describe.serial('whatsapp templates and token health', () => {
    test('settings syncs the template registry and switches reminders on', async ({ page }) => {
        await login(page, 'owner@riyadh-cooling.test');
        await page.goto('/settings/integrations/whatsapp');

        await page.getByLabel('Business account ID').fill('400000000000001');
        await page.getByRole('button', { name: 'Save' }).click();
        await page.getByRole('button', { name: 'Sync statuses from Meta' }).click();

        const templates = page.getByTestId('whatsapp-template');
        await expect(templates).toHaveCount(4);
        await expect(templates.filter({ hasText: 'Approved' })).toHaveCount(4);
        await expect(templates.first()).toContainText('{{1}} Customer first name');

        const toggle = page.getByTestId('whatsapp-reminders-toggle');
        await expect(toggle).not.toBeChecked();
        const saved = page.waitForResponse((response) => response.url().endsWith('/settings/integrations/whatsapp/reminders'));
        await toggle.check();
        await saved;
        await page.reload();
        await expect(page.getByTestId('whatsapp-reminders-toggle')).toBeChecked();
    });

    test('a closed 24-hour window replaces free text with approved templates', async ({ page }) => {
        const conversation = closedWindowConversation();
        await login(page, 'owner@riyadh-cooling.test');
        await page.goto(`/whatsapp/${conversation}`);

        const composer = page.getByTestId('whatsapp-window-closed');
        await expect(composer).toContainText('The 24-hour reply window is closed');
        await expect(page.getByRole('textbox', { name: 'Reply' })).toHaveCount(0);
        await expect(page.getByTestId('whatsapp-template-select').locator('option')).toHaveCount(4);
        await page.screenshot({ path: `${shots}/inbox-closed-window-en.png` });

        await useLocale(page, 'ar');
        await expect(composer).toContainText('انتهت نافذة الرد');
        await page.screenshot({ path: `${shots}/inbox-closed-window-ar.png` });
        await useLocale(page, 'en');

        await page.getByTestId('whatsapp-template-select').selectOption({ label: 'Appointment reminder · Arabic' });
        await page.getByRole('button', { name: 'Send template' }).click();
        await expect(page.getByTestId('whatsapp-template-label')).toContainText('Template: Appointment reminder');
        await expect(page.getByTestId('whatsapp-message').last()).toContainText('Huda');
    });

    test('a rejected access token shows the needs-attention banner until the connection works again', async ({ page }) => {
        await login(page, 'owner@riyadh-cooling.test');
        await page.goto('/settings/integrations/whatsapp');

        await page.getByLabel('Access token').fill('fake-invalid-token');
        await page.getByRole('button', { name: 'Save' }).click();
        await page.getByRole('button', { name: 'Verify connection' }).click();

        const banner = page.getByTestId('whatsapp-attention');
        await expect(banner).toContainText('WhatsApp needs attention');
        await expect(banner).toContainText('invalid or has expired');
        await expect(banner.getByRole('link', { name: 'Setup instructions' })).toHaveAttribute('href', '/whatsapp/setup-guide');
        await page.screenshot({ path: `${shots}/settings-banner-en.png` });
        await page.getByTestId('whatsapp-templates').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `${shots}/settings-templates-en.png` });

        await useLocale(page, 'ar');
        await expect(banner).toContainText('واتساب يحتاج إلى متابعة');
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: `${shots}/settings-banner-ar.png` });
        await page.getByTestId('whatsapp-templates').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `${shots}/settings-templates-ar.png` });
        await useLocale(page, 'en');

        await page.goto('/whatsapp');
        await expect(page.getByTestId('whatsapp-attention')).toBeVisible();
        await page.getByTestId('whatsapp-attention').getByRole('link', { name: 'Setup instructions' }).click();
        await expect(page.getByRole('heading', { name: 'WhatsApp setup instructions' })).toBeVisible();
        await expect(page.getByText('Token health (the "needs attention" banner)')).toBeVisible();

        await page.goto('/settings/integrations/whatsapp');
        await page.getByLabel('Access token').fill('seed-token-not-real');
        await page.getByRole('button', { name: 'Save' }).click();
        await page.getByRole('button', { name: 'Verify connection' }).click();
        await expect(page.getByText('WhatsApp is connected.')).toBeVisible();
        await expect(page.getByTestId('whatsapp-attention')).toHaveCount(0);
    });
});
