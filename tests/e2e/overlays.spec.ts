import { expect, type Page, test } from '@playwright/test';
import { login } from './helpers';

const locales = [
    { code: 'en', dir: 'ltr', moreActions: 'More actions', archive: 'Archive', cancel: 'Cancel', confirm: 'Confirm', question: 'Archive this branch?' },
    { code: 'ar', dir: 'rtl', moreActions: 'إجراءات أخرى', archive: 'أرشفة', cancel: 'إلغاء', confirm: 'تأكيد', question: 'أرشفة هذا الفرع؟' },
] as const;

async function openArchiveDialog(page: Page, locale: (typeof locales)[number]) {
    const trigger = page.getByRole('row').nth(1).getByRole('button', { name: locale.moreActions });
    await trigger.click();
    await page.getByRole('menuitem', { name: locale.archive }).click();

    const dialog = page.getByRole('alertdialog', { name: locale.question });
    await expect(dialog).toBeVisible();

    return { trigger, dialog };
}

test('confirm dialog traps focus, closes on Escape and returns focus in both locales', async ({ page }) => {
    await login(page, 'owner@riyadh-cooling.test');

    for (const locale of locales) {
        if (locale.code === 'ar') {
            await page.getByRole('button', { name: 'Arabic' }).click();
        }

        await page.goto('/branches');
        await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
        await expect(page.locator('html')).toHaveAttribute('dir', locale.dir);

        const { trigger, dialog } = await openArchiveDialog(page, locale);
        const cancel = dialog.getByRole('button', { name: locale.cancel });
        const confirm = dialog.getByRole('button', { name: locale.confirm });

        await expect(dialog).toHaveAttribute('aria-modal', 'true');
        await expect(cancel).toBeFocused();
        expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');

        // Tab and Shift+Tab wrap inside the dialog.
        await page.keyboard.press('Tab');
        await expect(confirm).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(cancel).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(confirm).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(trigger).toBeFocused();
        expect(await page.evaluate(() => document.body.style.overflow)).toBe('');

        // The backdrop cancels too, and focus still returns to the trigger.
        await openArchiveDialog(page, locale);
        await page.mouse.click(8, 8);
        await expect(page.getByRole('alertdialog')).toHaveCount(0);
        await expect(trigger).toBeFocused();
    }

    await page.getByRole('button', { name: 'English' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});
