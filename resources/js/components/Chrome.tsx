import { router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useState } from 'react';
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
        <div className="flex rounded-[10px] bg-zinc-100/90 p-0.5" role="group" aria-label={t.guest.locale}>
            {(['en', 'ar'] as const).map((code) => {
                const active = locale === code;
                const label = code === 'en' ? t.guest.english : t.guest.arabic;

                return (
                    <button
                        key={code}
                        type="button"
                        aria-pressed={active}
                        className={`rounded-[8px] px-2.5 py-1 text-xs font-medium transition-colors duration-200 ${active ? 'bg-white text-zinc-900 shadow-[0_1px_2px_rgba(16,24,40,0.06)]' : 'text-zinc-500 hover:text-zinc-800'}`}
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
            <div className="h-0.5 w-full bg-teal-600" />
        </div>
    );
}

export function FlashMessage() {
    const flash = usePage().props.flash;

    if (!flash.success && !flash.status) {
        return null;
    }

    return (
        <div className="mb-4 flex items-start gap-2 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 shadow-[0_1px_2px_rgba(16,24,40,0.04)]" role="status">
            <Check className="mt-0.5 size-4 shrink-0" strokeWidth={1.75} />
            <p>{flash.success ?? flash.status}</p>
        </div>
    );
}
