import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutDashboard, LogOut, Menu, Settings, Users } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { FlashMessage, useDocumentLocale } from '@/components/Chrome';
import { useI18n } from '@/i18n';

function can(permissions: string[], permission: string): boolean {
    return permissions.includes('*') || permissions.includes(permission);
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { t } = useI18n();
    const { auth, organization, permissions } = usePage().props;
    const [open, setOpen] = useState(false);
    useDocumentLocale();

    const items = [
        { href: '/', label: t.nav.dashboard, icon: LayoutDashboard, show: true },
        { href: '/branches', label: t.nav.branches, icon: Building2, show: can(permissions, 'branches.view') },
        { href: '/users', label: t.nav.team, icon: Users, show: can(permissions, 'users.view') },
        { href: '/organization', label: t.nav.organization, icon: Settings, show: can(permissions, 'organization.view') },
    ];

    return (
        <div className="min-h-screen bg-zinc-100 text-zinc-900">
            <aside
                className={`fixed inset-y-0 start-0 z-40 w-64 border-e border-zinc-800 bg-zinc-950 text-zinc-100 transition-transform lg:translate-x-0 ${
                    open ? 'translate-x-0' : 'ltr:-translate-x-full rtl:translate-x-full'
                }`}
            >
                <div className="border-b border-zinc-800 px-5 py-5">
                    <p className="text-xs font-medium tracking-widest text-teal-300">{t.appName}</p>
                    <p className="mt-2 truncate text-sm font-semibold">{organization?.name}</p>
                    <p className="truncate text-xs text-zinc-400">{organization?.role}</p>
                </div>
                <nav className="space-y-1 px-3 py-4">
                    {items
                        .filter((item) => item.show)
                        .map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-800"
                                onClick={() => setOpen(false)}
                            >
                                <item.icon className="size-4" />
                                {item.label}
                            </Link>
                        ))}
                </nav>
            </aside>
            {open ? <button type="button" className="fixed inset-0 z-30 bg-black/40 lg:hidden" onClick={() => setOpen(false)} aria-label="Close menu" /> : null}
            <div className="lg:ps-64">
                <header className="flex items-center justify-between border-b border-zinc-200 bg-white px-4 py-3">
                    <button type="button" className="rounded-md p-2 lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu">
                        <Menu className="size-5" />
                    </button>
                    <div className="ms-auto flex items-center gap-4 text-sm">
                        <span className="hidden text-zinc-600 sm:inline">{auth.user?.name}</span>
                        <Link href="/organizations" className="text-teal-800">
                            {t.nav.switchOrganization}
                        </Link>
                        <Link href="/logout" method="post" as="button" className="inline-flex items-center gap-1 text-zinc-700">
                            <LogOut className="size-4" />
                            {t.nav.signOut}
                        </Link>
                    </div>
                </header>
                <main className="px-4 py-6 sm:px-6">
                    <FlashMessage />
                    {children}
                </main>
            </div>
        </div>
    );
}
