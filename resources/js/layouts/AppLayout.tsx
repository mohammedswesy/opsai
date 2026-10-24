import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Columns3,
    Contact,
    Inbox,
    LayoutDashboard,
    ListTodo,
    LogOut,
    Menu,
    MessageCircle,
    Receipt,
    Search,
    Settings,
    Sparkles,
    UserCog,
    Users,
    Wallet,
    Wrench,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { Avatar } from '@/components/Avatar';
import { CommandPalette, type CommandLink } from '@/components/CommandPalette';
import { FlashMessage, LocaleSwitch, NavigationProgress, useDocumentLocale } from '@/components/Chrome';
import { PageMetaProvider, type PageMeta } from '@/components/PageTitle';
import { VisitProvider } from '@/components/VisitState';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import { can } from '@/lib/permissions';

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

type NavItem = { href: string; label: string; icon: LucideIcon; show: boolean; also?: string[] };
type NavGroup = { id: string; label: string; items: NavItem[] };

function isCurrent(item: NavItem, url: string): boolean {
    const path = url.split('?')[0] ?? '/';
    const matches = (href: string) => (href === '/' ? path === '/' : path === href || path.startsWith(`${href}/`));

    return matches(item.href) || (item.also ?? []).some(matches);
}

/**
 * Role-specific navigation. `primary` items move, in this order, into a "Your work"
 * group at the top; `hidden` items are left out of the sidebar and the command menu.
 * This is display only: permissions decide what exists, and direct URLs keep working.
 */
const ROLE_NAVIGATION: Record<string, { primary: string[]; hidden: string[] }> = {
    technician: { primary: ['/dispatch', '/work-orders', '/tasks', '/customers', '/ai'], hidden: ['/branches', '/organization'] },
    accountant: { primary: ['/invoices', '/payments', '/customers', '/work-orders', '/', '/ai'], hidden: ['/dispatch', '/requests'] },
};

export default function AppLayout({ children }: { children: ReactNode }) {
    const { t } = useI18n();
    const page = usePage();
    const { auth, organization, permissions } = page.props;
    const [open, setOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>(readCollapsedGroups);
    const [meta, setMeta] = useState<PageMeta | null>(null);
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

    const standardGroups: NavGroup[] = [
        {
            id: 'overview',
            label: t.nav.overview,
            items: [
                { href: '/', label: t.nav.dashboard, icon: LayoutDashboard, show: true },
                { href: '/ai', label: t.nav.assistant, icon: Sparkles, show: true },
                { href: '/whatsapp', label: t.nav.whatsapp, icon: MessageCircle, show: can(permissions, 'whatsapp.view'), also: ['/settings/integrations/whatsapp'] },
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

    const roleNavigation = organization ? ROLE_NAVIGATION[organization.role] : undefined;
    const allItems = standardGroups.flatMap((group) => group.items);
    const groups: NavGroup[] = roleNavigation
        ? [
              {
                  id: 'focus',
                  label: t.nav.yourWork,
                  items: roleNavigation.primary.map((href) => allItems.find((item) => item.href === href)).filter((item): item is NavItem => item !== undefined),
              },
              ...standardGroups.map((group) => ({
                  ...group,
                  items: group.items.filter((item) => !roleNavigation.primary.includes(item.href) && !roleNavigation.hidden.includes(item.href)),
              })),
          ]
        : standardGroups;
    const visibleGroups = groups.map((group) => ({ ...group, items: group.items.filter((item) => item.show) })).filter((group) => group.items.length > 0);

    const commandLinks = useMemo<CommandLink[]>(
        () => visibleGroups.flatMap((group) => group.items.map((item) => ({ href: item.href, label: item.label, group: group.label }))),
        [visibleGroups],
    );
    const navTitle = visibleGroups.flatMap((group) => group.items).find((item) => isCurrent(item, page.url))?.label;
    const title = meta?.title ?? navTitle ?? t.appName;
    const userName = auth.user?.name ?? '';
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
            <PageMetaProvider onChange={setMeta}>
                <div className="min-h-screen text-ink">
                    <NavigationProgress />
                    <CommandPalette
                        open={paletteOpen}
                        onClose={() => setPaletteOpen(false)}
                        links={commandLinks}
                        canSearchCustomers={can(permissions, 'customers.view')}
                        canSearchRequests={can(permissions, 'requests.view') && !roleNavigation?.hidden.includes('/requests')}
                    />
                    <aside
                        ref={drawerRef}
                        id="app-navigation"
                        className={`fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-surface transition-transform duration-200 ease-out print:hidden lg:translate-x-0 ${
                            open ? 'translate-x-0' : 'max-lg:-translate-x-full rtl:max-lg:translate-x-full'
                        }`}
                    >
                        <div className="px-3 pt-4">
                            <Link href="/organizations" className="flex items-center gap-2.5 rounded-control px-2 py-2 hover:bg-surface-muted" title={organization?.name} aria-label={t.nav.switchOrganization}>
                                <Avatar name={organization?.name ?? t.appName} shape="square" />
                                <span className="min-w-0">
                                    <span className="block truncate text-micro font-medium text-ink-muted">{t.appName}</span>
                                    <span dir="auto" className="block truncate text-body font-semibold text-ink">
                                        {organization?.name ?? t.nav.switchOrganization}
                                    </span>
                                </span>
                            </Link>
                        </div>
                        <nav className="mt-3 flex-1 space-y-4 overflow-y-auto px-3 pb-4" aria-label={t.appName}>
                            {visibleGroups.map((group) => {
                                const isCollapsed = collapsed[group.id] === true;

                                return (
                                    <div key={group.id}>
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between rounded-control px-2.5 py-1 text-micro font-semibold text-ink-muted hover:text-ink-secondary ltr:tracking-wide ltr:uppercase"
                                            aria-expanded={!isCollapsed}
                                            aria-label={`${isCollapsed ? t.nav.expandGroup : t.nav.collapseGroup} ${group.label}`}
                                            onClick={() => setCollapsed((current) => ({ ...current, [group.id]: !current[group.id] }))}
                                        >
                                            <span className="truncate">{group.label}</span>
                                            <ChevronDown className={`size-3.5 shrink-0 transition-transform duration-200 ${isCollapsed ? '-rotate-90 rtl:rotate-90' : ''}`} strokeWidth={1.75} aria-hidden="true" />
                                        </button>
                                        {isCollapsed ? null : (
                                            <div className="mt-1 space-y-0.5">
                                                {group.items.map((item) => {
                                                    const current = isCurrent(item, page.url);

                                                    return (
                                                        <Link
                                                            key={item.href}
                                                            href={item.href}
                                                            aria-current={current ? 'page' : undefined}
                                                            className={`group relative flex items-center gap-2.5 rounded-control px-2.5 py-2 text-body transition-colors duration-200 ${
                                                                current
                                                                    ? 'bg-brand-soft font-medium text-brand-strong before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded-badge before:bg-brand'
                                                                    : 'text-ink-secondary hover:bg-surface-muted hover:text-ink'
                                                            }`}
                                                            onClick={closeDrawer}
                                                        >
                                                            <item.icon
                                                                className={`size-[18px] shrink-0 ${current ? 'text-brand' : 'text-ink-subtle group-hover:text-ink-muted'}`}
                                                                strokeWidth={1.75}
                                                                aria-hidden="true"
                                                            />
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
                            <div className="border-t border-line p-3">
                                <div className="flex items-center gap-2.5 rounded-card bg-surface-muted px-2.5 py-2.5">
                                    <Avatar name={userName} tone="brand" />
                                    <span className="min-w-0">
                                        <span dir="auto" className="block truncate text-body font-medium text-ink" title={userName}>
                                            {userName}
                                        </span>
                                        <span className="block truncate text-caption text-ink-muted" title={roleLabel}>
                                            {roleLabel}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        ) : null}
                    </aside>
                    {open ? <button type="button" className="fixed inset-0 z-30 bg-ink/40 backdrop-blur-[1px] lg:hidden" onClick={closeDrawer} aria-label={t.nav.closeMenu} /> : null}
                    <div className="lg:ps-64 print:ps-0">
                        <header className="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-line bg-surface/80 px-3 backdrop-blur-md print:hidden sm:gap-3 sm:px-6">
                            <button
                                ref={menuButtonRef}
                                type="button"
                                className="shrink-0 rounded-control p-2 text-ink-secondary hover:bg-surface-muted lg:hidden"
                                onClick={() => setOpen(true)}
                                aria-label={t.nav.openMenu}
                                aria-expanded={open}
                                aria-controls="app-navigation"
                            >
                                <Menu className="size-5" strokeWidth={1.75} aria-hidden="true" />
                            </button>
                            <nav aria-label={t.nav.breadcrumb} className="min-w-0 flex-1">
                                <ol className="flex min-w-0 items-center gap-1.5 text-body">
                                    {meta?.parent ? (
                                        <li className="hidden min-w-0 shrink items-center gap-1.5 sm:flex">
                                            <Link href={meta.parent.href} className="truncate text-ink-muted hover:text-ink">
                                                {meta.parent.label}
                                            </Link>
                                            <ChevronRight className="size-3.5 shrink-0 text-ink-subtle rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                                        </li>
                                    ) : null}
                                    <li className="min-w-0 truncate font-medium text-ink" aria-current="page">
                                        <bdi>{title}</bdi>
                                    </li>
                                </ol>
                            </nav>
                            <button
                                type="button"
                                className="hidden h-9 w-full max-w-xs min-w-0 items-center gap-2 rounded-control bg-surface-muted px-3 text-body text-ink-muted hover:text-ink-secondary md:flex"
                                onClick={() => setPaletteOpen(true)}
                            >
                                <Search className="size-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                                <span className="truncate">{t.nav.searchPlaceholder}</span>
                                <kbd className="ms-auto rounded-control bg-surface px-1.5 py-0.5 text-micro font-medium text-ink-subtle shadow-control">{shortcut}</kbd>
                            </button>
                            <button type="button" className="shrink-0 rounded-control p-2 text-ink-muted hover:bg-surface-muted md:hidden" aria-label={t.nav.search} onClick={() => setPaletteOpen(true)}>
                                <Search className="size-[18px]" strokeWidth={1.75} aria-hidden="true" />
                            </button>
                            <div className="hidden shrink-0 sm:block">
                                <LocaleSwitch />
                            </div>
                            <div className="relative shrink-0" ref={accountRef}>
                                <button
                                    type="button"
                                    className="flex max-w-48 items-center gap-2 rounded-control py-1 ps-1 pe-1 hover:bg-surface-muted sm:pe-2"
                                    aria-label={t.nav.account}
                                    aria-expanded={accountOpen}
                                    aria-haspopup="menu"
                                    aria-controls={accountMenuId}
                                    onClick={() => setAccountOpen((current) => !current)}
                                >
                                    <Avatar name={userName} size="sm" tone="brand" />
                                    <span dir="auto" className="hidden truncate text-body font-medium text-ink sm:inline">
                                        {userName}
                                    </span>
                                </button>
                                {accountOpen ? (
                                    <div id={accountMenuId} role="menu" className="absolute end-0 z-30 mt-2 w-64 rounded-card border border-line bg-surface p-2 shadow-overlay">
                                        <div className="px-2 py-2">
                                            <p dir="auto" className="truncate text-body font-semibold text-ink" title={userName}>
                                                {userName}
                                            </p>
                                            <p className="mt-0.5 truncate text-caption text-ink-muted" title={auth.user?.email}>
                                                <bdi dir="ltr">{auth.user?.email}</bdi>
                                            </p>
                                            {organization ? (
                                                <p className="mt-2 truncate text-caption text-ink-secondary" title={`${organization.name} · ${roleLabel}`}>
                                                    <span dir="auto">{organization.name}</span>
                                                    <span className="text-ink-subtle"> · {roleLabel}</span>
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="flex items-center justify-between gap-3 border-t border-line px-2 py-2 sm:hidden">
                                            <span className="text-caption text-ink-muted">{t.nav.language}</span>
                                            <LocaleSwitch />
                                        </div>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            role="menuitem"
                                            className="flex w-full items-center gap-2 rounded-control px-2 py-2 text-body text-ink-secondary hover:bg-surface-muted hover:text-ink"
                                        >
                                            <LogOut className="size-4" strokeWidth={1.75} aria-hidden="true" />
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
            </PageMetaProvider>
        </VisitProvider>
    );
}
