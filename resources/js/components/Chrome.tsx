import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert } from '@/components/Toast';
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

    return (
        <div className="flex rounded-control bg-surface-muted p-0.5" role="group" aria-label={t.guest.locale}>
            {(['en', 'ar'] as const).map((code) => {
                const active = locale === code;
                const label = code === 'en' ? t.guest.english : t.guest.arabic;

                return (
                    <button
                        key={code}
                        type="button"
                        aria-pressed={active}
                        className={`rounded-control px-2.5 py-1 text-caption font-medium transition-colors duration-200 ${active ? 'bg-surface text-ink shadow-control' : 'text-ink-muted hover:text-ink'}`}
                        onClick={() => {
                            if (!active) {
                                router.post('/locale', { locale: code });
                            }
                        }}
                    >
                        {label}
                    </button>
                );
            })}
        </div>
    );
}

export function NavigationProgress() {
    const [active, setActive] = useState(false);
    const { t } = useI18n();

    useEffect(() => {
        const removeStart = router.on('start', () => setActive(true));
        const removeFinish = router.on('finish', () => setActive(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    if (!active) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 top-0 z-50" role="progressbar" aria-label={t.common.loading}>
            <div className="h-0.5 w-full bg-brand" />
        </div>
    );
}

export function FlashMessage() {
    const flash = usePage().props.flash;

    if (!flash.success && !flash.status) {
        return null;
    }

    return (
        <Alert tone="success" className="mb-4">
            <p>{flash.success ?? flash.status}</p>
        </Alert>
    );
}
