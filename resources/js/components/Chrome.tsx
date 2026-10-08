import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useI18n } from '@/i18n';

export function useDocumentLocale(): void {
    const { locale, dir } = useI18n();

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [locale, dir]);
}

export function LocaleSwitch() {
    const { locale, t } = useI18n();
    const next = locale === 'ar' ? 'en' : 'ar';

    return (
        <button
            type="button"
            className="text-sm font-medium text-teal-800"
            onClick={() => router.post('/locale', { locale: next })}
        >
            {locale === 'ar' ? t.guest.english : t.guest.arabic}
        </button>
    );
}

export function FlashMessage() {
    const flash = usePage().props.flash;

    if (!flash.success && !flash.status) {
        return null;
    }

    return (
        <div className="mb-4 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            {flash.success ?? flash.status}
        </div>
    );
}
