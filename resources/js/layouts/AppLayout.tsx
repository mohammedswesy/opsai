import { Link, usePage } from '@inertiajs/react';
import { Building2, CalendarDays, ChevronDown, ClipboardList, Columns3, Contact, Inbox, LayoutDashboard, ListTodo, LogOut, Menu, MessageCircle, Receipt, Search, Settings, Sparkles, UserCog, Users, Wallet, Wrench, type LucideIcon } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { CommandPalette, type CommandLink } from '@/components/CommandPalette';
import { FlashMessage, LocaleSwitch, NavigationProgress, useDocumentLocale } from '@/components/Chrome';
import { VisitProvider } from '@/components/VisitState';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';

const NAV_GROUPS_KEY = 'opsai.nav.groups';

function readCollapsedGroups(): Record<string, boolean> {
    try {
        const raw = localStorage.getItem(NAV_GROUPS_KEY);

        if (!raw) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return {};
        }

        return Object.fromEntries(Object.entries(parsed).filter((entry): entry is [string, boolean] => typeof entry[1] === 'boolean'));
    } catch {
        return {};
    }
}

function can(permissions: string[], permission: string): boolean {
    return permissions.includes('*') || permissions.includes(permission);
}

function isCurrent(href: string, url: string): boolean {
    const path = url.split('?')[0] ?? '/';

    if (href === '/') {
        return path === '/';
    }

    return path === href || path.startsWith(`${href}/`);
}

type NavItem = { href: string; label: string; icon: LucideIcon; show: boolean };
type NavGroup = { id: string; label: string; items: NavItem[] };

export default function AppLayout({ children }: { children: ReactNode }) {
    const { t } = useI18n();
    const page = usePage();
    const { auth, organization, permissions } = page.props;
    const [open, setOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>(readCollapsedGroups);
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const drawerRef = useRef<HTMLElement>(null);
    const accountRef = useRef<HTMLDivElement>(null);
    const accountMenuId = useId();
    useDocumentLocale();
    const shortcut = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘K' : 'Ctrl K';

    useEffect(() => {
        try {
            localStorage.setItem(NAV_GROUPS_KEY, JSON.stringify(collapsed));
        } catch {
            // The menu still works when storage is blocked.
        }
    }, [collapsed]);

    const groups: NavGroup[] = [
        {
            id: 'overview',
            label: t.nav.overview,
            items: [
                { href: '/', label: t.nav.dashboard, icon: LayoutDashboard, show: true },
                { href: '/ai', label: t.nav.assistant, icon: Sparkles, show: true },
                { href: '/whatsapp', label: t.nav.whatsapp, icon: MessageCircle, show: can(permissions, 'whatsapp.view') },
            ],
        },
        {
            id: 'operations',
            label: t.nav.operations,
            items: [
                { href: '/branches', label: t.nav.branches, icon: Building2, show: can(permissions, 'branches.view') },
                { href: '/customers', label: t.nav.customers, icon: Contact, show: can(permissions, 'customers.view') },
                { href: '/services', label: t.nav.services, icon: Wrench, show: can(permissions, 'services.view') },
                { href: '/technicians', label: t.nav.technicians, icon: UserCog, show: can(permissions, 'technicians.view') },
                { href: '/requests', label: t.nav.requests, icon: Inbox, show: can(permissions, 'requests.view') },
                { href: '/appointments', label: t.nav.appointments, icon: CalendarDays, show: can(permissions, 'appointments.view') },
                { href: '/dispatch', label: t.nav.dispatch, icon: Columns3, show: can(permissions, 'appointments.view') || can(permissions, 'work_orders.view') },
                { href: '/work-orders', label: t.nav.workOrders, icon: ClipboardList, show: can(permissions, 'work_orders.view') },
                { href: '/tasks', label: t.nav.tasks, icon: ListTodo, show: can(permissions, 'tasks.view') },
            ],
        },
        {
            id: 'finance',
            label: t.nav.finance,
            items: [
                { href: '/invoices', label: t.nav.invoices, icon: Receipt, show: can(permissions, 'invoices.view') },
                { href: '/payments', label: t.nav.payments, icon: Wallet, show: can(permissions, 'payments.view') },
            ],
        },
        {
            id: 'workspace',
            label: t.nav.workspace,
            items: [
                { href: '/users', label: t.nav.team, icon: Users, show: can(permissions, 'users.view') },
                { href: '/organization', label: t.nav.organization, icon: Settings, show: can(permissions, 'organization.view') },
            ],
        },
    ];

    const commandLinks = useMemo<CommandLink[]>(
        () => groups.flatMap((group) => group.items.filter((item) => item.show).map((item) => ({ href: item.href, label: item.label, group: group.label }))),
        [groups],
    );
    const currentLabel = commandLinks.find((item) => isCurrent(item.href, page.url))?.label ?? t.nav.dashboard;
    const initial = auth.user?.name?.trim().charAt(0).toUpperCase() ?? 'O';
    const roleLabel = organization ? optionLabel(t.labels, organization.role) : '';

    useEffect(() => {
        if (!open) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        drawerRef.current?.querySelector<HTMLElement>('a, button')?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [open]);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPaletteOpen(true);
                setAccountOpen(false);
            }

            if (event.key !== 'Escape') {
                return;
            }

            if (paletteOpen) {
                setPaletteOpen(false);
            }

            if (accountOpen) {
                setAccountOpen(false);
            }

            if (open) {
                setOpen(false);
                menuButtonRef.current?.focus();
            }
        }

        function onPointerDown(event: PointerEvent) {
            if (accountRef.current && !accountRef.current.contains(event.target as Node)) {
                setAccountOpen(false);
            }
        }

        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [accountOpen, open, paletteOpen]);

    function closeDrawer() {
        setOpen(false);
    }

    return (
        <VisitProvider>
        <div className="min-h-screen text-zinc-900">
            <NavigationProgress />
            <CommandPalette
                open={paletteOpen}
                onClose={() => setPaletteOpen(false)}
                links={commandLinks}
                canSearchCustomers={can(permissions, 'customers.view')}
                canSearchRequests={can(permissions, 'requests.view')}
            />
            <aside
                ref={drawerRef}
                id="app-navigation"
                className={`fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-zinc-200/70 bg-white transition-transform duration-200 ease-out print:hidden lg:translate-x-0 ${
                    open ? 'translate-x-0' : 'max-lg:-translate-x-full rtl:max-lg:translate-x-full'
                }`}
            >
                <div className="px-3 pt-4">
                    <Link href="/organizations" className="flex items-center gap-2.5 rounded-xl px-2 py-2 hover:bg-zinc-50" title={organization?.name} aria-label={t.nav.switchOrganization}>
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-sm font-semibold text-white">O</span>
                        <span className="min-w-0">
                            <span className="block truncate text-[11px] font-medium tracking-wide text-zinc-400 uppercase">{t.appName}</span>
                            <span dir="auto" className="block truncate text-sm font-semibold text-zinc-900">{organization?.name ?? t.nav.switchOrganization}</span>
                        </span>
                    </Link>
                </div>
                <nav className="mt-3 flex-1 space-y-4 overflow-y-auto px-3 pb-4" aria-label={t.appName}>
                    {groups.map((group) => {
                        const visible = group.items.filter((item) => item.show);

                        if (visible.length === 0) {
                            return null;
                        }

                        const isCollapsed = collapsed[group.id] === true;

                        return (
                            <div key={group.id}>
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between rounded-lg px-2.5 py-1 text-[11px] font-semibold tracking-wide text-zinc-400 uppercase hover:text-zinc-600"
                                    aria-expanded={!isCollapsed}
                                    aria-label={`${isCollapsed ? t.nav.expandGroup : t.nav.collapseGroup} ${group.label}`}
                                    onClick={() => setCollapsed((current) => ({ ...current, [group.id]: !current[group.id] }))}
                                >
                                    <span className="truncate">{group.label}</span>
                                    <ChevronDown className={`size-3.5 shrink-0 transition-transform duration-200 ${isCollapsed ? '-rotate-90 rtl:rotate-90' : ''}`} strokeWidth={1.75} />
                                </button>
                                {isCollapsed ? null : (
                                    <div className="mt-1 space-y-0.5">
                                        {visible.map((item) => {
                                            const current = isCurrent(item.href, page.url);

                                            return (
                                                <Link
                                                    key={item.href}
                                                    href={item.href}
                                                    aria-current={current ? 'page' : undefined}
                                                    className={`relative flex items-center gap-2.5 rounded-[10px] px-2.5 py-2 text-sm transition-colors duration-200 ${
                                                        current
                                                            ? 'bg-blue-50 font-medium text-blue-700 before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded-full before:bg-blue-600'
                                                            : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900'
                                                    }`}
                                                    onClick={closeDrawer}
                                                >
                                                    <item.icon className={`size-[18px] shrink-0 ${current ? 'text-blue-600' : 'text-zinc-400'}`} strokeWidth={1.75} />
                                                    <span className="truncate">{item.label}</span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </nav>
                {auth.user ? (
                    <div className="border-t border-zinc-200/70 p-3">
                        <div className="flex items-center gap-2.5 rounded-2xl bg-zinc-50 px-2.5 py-2.5">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">{initial}</span>
                            <span className="min-w-0">
                                <span dir="auto" className="block truncate text-sm font-medium text-zinc-900" title={auth.user.name}>{auth.user.name}</span>
                                <span className="block truncate text-xs text-zinc-500" title={roleLabel}>{roleLabel}</span>
                            </span>
                        </div>
                    </div>
                ) : null}
            </aside>
            {open ? (
                <button type="button" className="fixed inset-0 z-30 bg-zinc-950/40 backdrop-blur-[1px] lg:hidden" onClick={closeDrawer} aria-label={t.nav.closeMenu} />
            ) : null}
            <div className="lg:ps-64 print:ps-0">
                <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-zinc-200/70 bg-white/80 px-4 backdrop-blur-md print:hidden sm:px-6">
                    <button
                        ref={menuButtonRef}
                        type="button"
                        className="rounded-[10px] p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden"
                        onClick={() => setOpen(true)}
                        aria-label={t.nav.openMenu}
                        aria-expanded={open}
                        aria-controls="app-navigation"
                    >
                        <Menu className="size-5" strokeWidth={1.75} />
                    </button>
                    <p className="min-w-0 truncate text-sm font-medium text-zinc-900">{currentLabel}</p>
                    <button
                        type="button"
                        className="ms-auto hidden h-9 min-w-0 max-w-sm flex-1 items-center gap-2 rounded-[10px] border border-transparent bg-zinc-100/80 px-3 text-sm text-zinc-500 md:flex"
                        onClick={() => setPaletteOpen(true)}
                    >
                        <Search className="size-4" strokeWidth={1.75} />
                        <span className="truncate">{t.nav.searchPlaceholder}</span>
                        <kbd className="ms-auto rounded-md bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-400 shadow-[0_1px_1px_rgba(16,24,40,0.04)]">{shortcut}</kbd>
                    </button>
                    <button type="button" className="ms-auto rounded-[10px] p-2 text-zinc-500 hover:bg-zinc-100 md:hidden" aria-label={t.nav.search} onClick={() => setPaletteOpen(true)}>
                        <Search className="size-[18px]" strokeWidth={1.75} />
                    </button>
                    <LocaleSwitch />
                    <div className="relative" ref={accountRef}>
                        <button
                            type="button"
                            className="flex max-w-48 items-center gap-2 rounded-[10px] py-1 ps-1 pe-2 hover:bg-zinc-100"
                            aria-label={t.nav.account}
                            aria-expanded={accountOpen}
                            aria-haspopup="menu"
                            aria-controls={accountMenuId}
                            onClick={() => setAccountOpen((current) => !current)}
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{initial}</span>
                            <span dir="auto" className="hidden truncate text-sm font-medium text-zinc-800 sm:inline">{auth.user?.name}</span>
                        </button>
                        {accountOpen ? (
                            <div id={accountMenuId} role="menu" className="absolute end-0 z-30 mt-2 w-64 rounded-2xl border border-zinc-200/70 bg-white p-2 shadow-[0_24px_48px_-12px_rgba(16,24,40,0.22)]">
                                <div className="px-2 py-2">
                                    <p dir="auto" className="truncate text-sm font-semibold text-zinc-900" title={auth.user?.name}>{auth.user?.name}</p>
                                    <p className="mt-0.5 truncate text-xs text-zinc-500" title={auth.user?.email}>{auth.user?.email}</p>
                                    {organization ? (
                                        <p className="mt-2 truncate text-xs text-zinc-600" title={`${organization.name} · ${roleLabel}`}>
                                            <span dir="auto">{organization.name}</span>
                                            <span className="text-zinc-400"> · {roleLabel}</span>
                                        </p>
                                    ) : null}
                                </div>
                                <Link href="/logout" method="post" as="button" role="menuitem" className="flex w-full items-center gap-2 rounded-[10px] px-2 py-2 text-sm text-zinc-700 hover:bg-zinc-50">
                                    <LogOut className="size-4" strokeWidth={1.75} />
                                    {t.nav.signOut}
                                </Link>
                            </div>
                        ) : null}
                    </div>
                </header>
                <main className="mx-auto w-full max-w-[1280px] px-4 py-8 sm:px-6 lg:px-8">
                    <FlashMessage />
                    {children}
                </main>
            </div>
        </div>
        </VisitProvider>
    );
}
