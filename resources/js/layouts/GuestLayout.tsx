import { Link } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import type { ReactNode } from 'react';
import { FlashMessage, LocaleSwitch, NavigationProgress, useDocumentLocale } from '@/components/Chrome';
import { useI18n } from '@/i18n';

function BrandMark() {
    const { t } = useI18n();

    return (
        <Link href="/login" className="inline-flex items-center gap-2.5 rounded-control">
            <span aria-hidden="true" className="flex size-9 items-center justify-center rounded-control bg-brand text-on-brand shadow-control">
                <Wrench className="size-[18px]" strokeWidth={2} />
            </span>
            <span className="text-heading font-semibold text-ink">{t.appName}</span>
        </Link>
    );
}

/**
 * Sign-in and sign-up shell: brand mark and language switch on top, the tagline, and
 * the form in a card. `wide` gives two-column forms (Register) room at sm and up.
 */
export default function GuestLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const { t } = useI18n();
    useDocumentLocale();

    return (
        <div className="flex min-h-screen flex-col bg-canvas text-ink">
            <NavigationProgress />
            <header className="flex h-16 items-center justify-between px-4 sm:px-6">
                <BrandMark />
                <LocaleSwitch />
            </header>
            <main className="flex flex-1 flex-col items-center justify-center px-4 pt-4 pb-12">
                <p className="mb-6 max-w-md text-center text-body text-ink-secondary">{t.guest.tagline}</p>
                <div className={`w-full rounded-card border border-line bg-surface p-6 shadow-card sm:p-8 ${wide ? 'max-w-form-wide' : 'max-w-md'}`}>
                    <FlashMessage />
                    {children}
                </div>
            </main>
        </div>
    );
}
