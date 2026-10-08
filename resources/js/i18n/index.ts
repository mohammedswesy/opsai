import { usePage } from '@inertiajs/react';
import { ar } from './ar';
import { en, type Messages } from './en';

export function useI18n(): { t: Messages; locale: string; dir: 'ltr' | 'rtl' } {
    const { locale, dir } = usePage().props;
    const resolvedLocale = locale === 'ar' ? 'ar' : 'en';

    return {
        t: resolvedLocale === 'ar' ? ar : en,
        locale: resolvedLocale,
        dir: dir === 'rtl' ? 'rtl' : 'ltr',
    };
}
