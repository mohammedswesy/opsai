import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FlashMessage, LocaleSwitch, useDocumentLocale } from '@/components/Chrome';
import { useI18n } from '@/i18n';

export default function GuestLayout({ children }: { children: ReactNode }) {
    const { t } = useI18n();
    useDocumentLocale();

    return (
        <div className="flex min-h-screen flex-col bg-zinc-100">
            <header className="flex items-center justify-between px-6 py-4">
                <Link href="/login" className="text-sm font-semibold tracking-wide text-zinc-900">
                    {t.appName}
                </Link>
                <LocaleSwitch />
            </header>
            <main className="flex flex-1 items-center justify-center px-4 py-10">
                <div className="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                    <FlashMessage />
                    {children}
                </div>
            </main>
        </div>
    );
}
