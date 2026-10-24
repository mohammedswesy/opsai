import { router, usePage } from '@inertiajs/react';
import { Building2, CalendarDays, CircleDollarSign, ClipboardList, Columns3, Inbox, ListTodo, Plus, Receipt, UserCog, Users, Wallet } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Card } from '@/components/Card';
import { controlClassName } from '@/components/FormField';
import { Initials } from '@/components/Initials';
import { KpiCard } from '@/components/KpiCard';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { useCan } from '@/lib/permissions';
import type { Option } from '@/types/page';

type Metrics = {
    open_requests: number;
    todays_appointments: number;
    active_work_orders: number;
    completed_work_orders: number;
    available_technicians: number;
    pending_tasks: number;
    branches: number;
    members: number;
    requests_by_day: { date: string; count: number }[];
    work_orders_by_status: { status: string; count: number }[];
    technician_workload: { name: string; count: number }[];
};

type Finance = {
    currency: string;
    revenue: string;
    invoiced: string;
    paid: string;
    outstanding: string;
    overdue: string;
    todays_payments: string;
    by_day: { date: string; revenue: string; payments: string }[];
    invoice_status: { status: string; count: number }[];
};

const avatarTones = [
    'bg-blue-50 text-blue-700',
    'bg-indigo-50 text-indigo-700',
    'bg-sky-50 text-sky-700',
    'bg-emerald-50 text-emerald-700',
    'bg-violet-50 text-violet-700',
    'bg-amber-50 text-amber-800',
];

function fill(template: string, values: Record<string, number>): string {
    return Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`{${key}}`, String(value)), template);
}

function greetingKey(hour: number): 'goodMorning' | 'goodAfternoon' | 'goodEvening' {
    if (hour < 12) {
        return 'goodMorning';
    }

    if (hour < 17) {
        return 'goodAfternoon';
    }

    return 'goodEvening';
}

export default function Dashboard({
    metrics,
    finance,
    branches,
    filters,
}: {
    metrics: Metrics;
    finance: Finance | null;
    branches: Option[];
    filters: { branch_id?: string; from?: string; to?: string };
}) {
    const { t, locale } = useI18n();
    const { auth } = usePage().props;
    const can = useCan();
    const format = useFormat();
    const hour = new Date().getHours();
    const name = auth.user?.name ?? '';
    const today = format.date(new Date(), 'long');
    const waiting = metrics.open_requests + metrics.pending_tasks;
    const summary = waiting === 0 && metrics.todays_appointments === 0 && metrics.active_work_orders === 0
        ? t.dashboard.summaryQuiet
        : fill(t.dashboard.summary, {
            requests: metrics.open_requests,
            appointments: metrics.todays_appointments,
            jobs: metrics.active_work_orders,
        });
    const maxLoad = Math.max(1, ...metrics.technician_workload.map((row) => row.count));
    const maxOrders = Math.max(1, ...metrics.work_orders_by_status.map((row) => row.count));
    const maxStatus = Math.max(1, ...(finance?.invoice_status.map((row) => row.count) ?? [1]));
    const requestsEmpty = metrics.requests_by_day.every((day) => day.count === 0);
    const ordersEmpty = metrics.work_orders_by_status.every((row) => row.count === 0);
    const financeDaysEmpty = finance?.by_day.every((day) => Number(day.revenue) === 0 && Number(day.payments) === 0) ?? true;
    const invoiceStatusEmpty = finance?.invoice_status.every((row) => row.count === 0) ?? true;
    const canRequest = can('requests.manage');
    const canDispatch = can('appointments.view') || can('work_orders.view');

    function apply(next: { branch_id?: string; from?: string; to?: string }) {
        router.get('/', { ...filters, ...next }, { preserveState: true });
    }

    return (
        <div className="space-y-6">
            <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-zinc-400 uppercase">{today}</p>
                    <h1 dir="auto" className="mt-1 truncate text-[26px] leading-8 font-semibold tracking-tight text-zinc-900">
                        {t.dashboard[greetingKey(hour)]}{locale === 'ar' ? '، ' : ', '}{name}
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-zinc-600">{summary}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canRequest ? (
                        <ButtonLink href="/requests/create">
                            <Plus className="size-4" strokeWidth={1.75} />
                            {t.dashboard.newRequest}
                        </ButtonLink>
                    ) : null}
                    {canDispatch ? (
                        <ButtonLink href="/dispatch" variant="secondary">
                            <Columns3 className="size-4" strokeWidth={1.75} />
                            {t.dashboard.openDispatch}
                        </ButtonLink>
                    ) : null}
                </div>
            </header>

            <form className="flex flex-wrap items-end gap-3 rounded-2xl border border-zinc-200/70 bg-white/80 px-4 py-3">
                    <label className="block min-w-48 flex-1">
                        <span className="mb-1.5 block text-xs font-medium text-zinc-500">{t.dashboard.allBranches}</span>
                        <select
                            className={controlClassName}
                            name="branch_id"
                            defaultValue={filters.branch_id ?? ''}
                            aria-label={t.dashboard.allBranches}
                            onChange={(event) => apply({ branch_id: event.target.value })}
                        >
                            <option value="">{t.dashboard.allBranches}</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>{branch.name}</option>
                            ))}
                        </select>
                    </label>
                    <DateField label={t.dashboard.from} value={filters.from ?? ''} placeholder={t.dashboard.pickDate} onChange={(from) => apply({ from })} />
                    <DateField label={t.dashboard.to} value={filters.to ?? ''} placeholder={t.dashboard.pickDate} onChange={(to) => apply({ to })} />
                </form>

            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard icon={CalendarDays} tone="info" label={t.dashboard.todaysAppointments} value={metrics.todays_appointments} />
                <KpiCard icon={Inbox} tone="warning" label={t.dashboard.openRequests} value={metrics.open_requests} />
                <KpiCard icon={ListTodo} tone="warning" label={t.dashboard.pendingTasks} value={metrics.pending_tasks} />
                <KpiCard icon={ClipboardList} label={t.dashboard.activeWorkOrders} value={metrics.active_work_orders} />
            </section>

            <section className="grid gap-3 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <Card className="flex flex-col p-5">
                    <h2 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.todayOverview}</h2>
                    <div className="mt-6 space-y-6">
                        <Ring label={t.dashboard.completedWorkOrders} value={metrics.completed_work_orders} total={metrics.completed_work_orders + metrics.active_work_orders} />
                        <Ring label={t.dashboard.activeWorkOrders} value={metrics.active_work_orders} total={metrics.completed_work_orders + metrics.active_work_orders} />
                    </div>
                    <p className="mt-auto border-t border-zinc-100 pt-4 text-sm text-zinc-500">
                        <span className="font-semibold text-zinc-900 tabular-nums">{metrics.todays_appointments}</span>
                        <span className="ms-2">{t.dashboard.todaysAppointments}</span>
                    </p>
                </Card>
                <Card className="p-5">
                    <h2 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.technicianWorkload}</h2>
                    {metrics.technician_workload.length === 0 ? (
                        <p className="mt-6 text-sm text-zinc-500">{t.ops.empty}</p>
                    ) : (
                        <ul className="mt-4 space-y-3">
                            {metrics.technician_workload.map((row) => {
                                const hash = [...row.name].reduce((sum, char) => sum + char.charCodeAt(0), 0);

                                return (
                                    <li key={row.name} className="flex items-center gap-3">
                                        <Initials name={row.name} className={avatarTones[hash % avatarTones.length]} />
                                        <span dir="auto" className="w-36 truncate text-sm text-zinc-700 sm:w-44" title={row.name}>{row.name}</span>
                                        <div className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                            <div className="h-1.5 rounded-full bg-blue-600" style={{ width: `${Math.max(row.count === 0 ? 0 : 4, (row.count / maxLoad) * 100)}%` }} />
                                        </div>
                                        <span className="w-6 text-end text-sm font-medium text-zinc-700 tabular-nums">{row.count}</span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>
            </section>

            <section className="grid gap-3 lg:grid-cols-3">
                <Card className="p-5 lg:col-span-2">
                    <h2 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.requestsByDay}</h2>
                    {requestsEmpty ? <p className="mt-8 text-sm text-zinc-500">{t.dashboard.emptyChart}</p> : (
                        <ColumnChart rows={metrics.requests_by_day.map((day) => ({ key: day.date, date: day.date, value: day.count, title: `${format.date(day.date)} · ${day.count}` }))} />
                    )}
                </Card>
                <Card className="p-5">
                    <h2 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.workOrdersByStatus}</h2>
                    {ordersEmpty ? <p className="mt-8 text-sm text-zinc-500">{t.dashboard.emptyChart}</p> : (
                        <ul className="mt-4 space-y-3">
                            {metrics.work_orders_by_status.filter((row) => row.count > 0).map((row) => (
                                <li key={row.status}>
                                    <div className="mb-1.5 flex items-center justify-between gap-3">
                                        <StatusBadge value={row.status} />
                                        <span className="text-xs font-medium text-zinc-600 tabular-nums">{row.count}</span>
                                    </div>
                                    <div className="h-1.5 rounded-full bg-zinc-100">
                                        <div className="h-1.5 rounded-full bg-blue-600" style={{ width: `${(row.count / maxOrders) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </section>

            {finance ? (
                <section className="space-y-3" data-testid="finance-metrics">
                    <h2 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.nav.finance}</h2>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <KpiCard icon={Receipt} tone="warning" label={t.dashboard.outstanding} value={format.money(finance.outstanding, finance.currency)} />
                        <KpiCard icon={CircleDollarSign} tone="danger" label={t.dashboard.overdue} value={format.money(finance.overdue, finance.currency)} />
                    </div>
                    <div className="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.8fr)]">
                    <Card className="p-5">
                        <div className="mb-1 flex items-baseline justify-between gap-3">
                            <h3 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.revenueByDay}</h3>
                            <p className="text-sm font-medium text-zinc-700 tabular-nums">{format.money(finance.revenue, finance.currency)}</p>
                        </div>
                        {financeDaysEmpty ? <p className="mt-8 text-sm text-zinc-500">{t.dashboard.emptyChart}</p> : (
                            <ColumnChart
                                gradient
                                rows={finance.by_day.map((day) => ({
                                    key: day.date,
                                    date: day.date,
                                    value: Number(day.revenue),
                                    title: `${format.date(day.date)} · ${format.money(day.revenue, finance.currency)}`,
                                }))}
                            />
                        )}
                    </Card>
                    <Card className="p-5">
                        <h3 className="text-[15px] leading-5 font-semibold text-zinc-900">{t.dashboard.invoiceStatus}</h3>
                        {invoiceStatusEmpty ? <p className="mt-6 text-sm text-zinc-500">{t.dashboard.emptyChart}</p> : (
                            <ul className="mt-4 space-y-3">
                                {finance.invoice_status.filter((row) => row.count > 0).map((row) => (
                                    <li key={row.status}>
                                        <div className="mb-1.5 flex items-center justify-between gap-3">
                                            <StatusBadge value={row.status} />
                                            <span className="text-xs font-medium text-zinc-600 tabular-nums">{row.count}</span>
                                        </div>
                                        <div className="h-1.5 rounded-full bg-zinc-100">
                                            <div className="h-1.5 rounded-full bg-indigo-500" style={{ width: `${(row.count / maxStatus) * 100}%` }} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <KpiCard size="quiet" icon={Receipt} label={t.dashboard.invoiced} value={format.money(finance.invoiced, finance.currency)} />
                        <KpiCard size="quiet" icon={Wallet} tone="success" label={t.dashboard.paid} value={format.money(finance.paid, finance.currency)} />
                        <KpiCard size="quiet" icon={Wallet} tone="info" label={t.dashboard.todaysPayments} value={format.money(finance.todays_payments, finance.currency)} />
                    </div>
                </section>
            ) : null}

            <section>
                <h2 className="mb-3 text-xs font-semibold tracking-wide text-zinc-400 uppercase">{t.dashboard.secondary}</h2>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard size="quiet" icon={Building2} label={t.dashboard.branches} value={metrics.branches} />
                    <KpiCard size="quiet" icon={Users} label={t.dashboard.members} value={metrics.members} />
                    <KpiCard size="quiet" icon={UserCog} tone="success" label={t.dashboard.availableTechnicians} value={metrics.available_technicians} />
                    <KpiCard size="quiet" icon={ClipboardList} tone="success" label={t.dashboard.completedWorkOrders} value={metrics.completed_work_orders} />
                </div>
            </section>
        </div>
    );
}

function DateField({ label, value, placeholder, onChange }: { label: string; value: string; placeholder: string; onChange: (value: string) => void }) {
    const format = useFormat();

    return (
        <label className="block min-w-44">
            <span className="mb-1.5 block text-xs font-medium text-zinc-500">{label}</span>
            <span className="relative block">
                <input
                    className={`${controlClassName} date-mask`}
                    type="date"
                    aria-label={label}
                    defaultValue={value}
                    onChange={(event) => onChange(event.target.value)}
                />
                <span className="pointer-events-none absolute inset-y-0 start-3 end-9 flex items-center truncate text-sm text-zinc-800">
                    {value ? format.date(value) : <span className="text-zinc-400">{placeholder}</span>}
                </span>
            </span>
        </label>
    );
}

function ColumnChart({ rows, gradient = false }: { rows: { key: string; date: string; value: number; title: string }[]; gradient?: boolean }) {
    const format = useFormat();
    const max = Math.max(1, ...rows.map((row) => row.value));
    const gradientId = useId().replace(/:/g, '');

    return (
        <div className="mt-5">
            <svg viewBox="0 0 100 36" className="h-36 w-full overflow-visible" role="img">
                {gradient ? (
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#60A5FA" />
                            <stop offset="100%" stopColor="#2563EB" />
                        </linearGradient>
                    </defs>
                ) : null}
                {[12, 24].map((line) => (
                    <line key={line} x1="0" x2="100" y1={line} y2={line} stroke="#e8eef6" strokeWidth="0.35" />
                ))}
                <line x1="0" x2="100" y1="34" y2="34" stroke="#e2e8f0" strokeWidth="0.35" />
                {rows.map((row, index) => {
                    if (row.value <= 0) {
                        return null;
                    }

                    const slot = 100 / rows.length;
                    const height = Math.max(1.6, (row.value / max) * 30);
                    const width = slot * 0.62;
                    const x = index * slot + (slot - width) / 2;

                    return (
                        <rect key={row.key} x={x} y={34 - height} width={width} height={height} rx="1.1" fill={gradient ? `url(#${gradientId})` : '#2563EB'}>
                            <title>{row.title}</title>
                        </rect>
                    );
                })}
            </svg>
            <div className="mt-2 flex">
                {rows.map((row, index) => (
                    <span key={row.key} className="min-w-0 flex-1 truncate text-center text-[10px] text-zinc-400 tabular-nums" title={row.title}>
                        {index % 2 === 0 ? format.date(row.date, 'short') : ''}
                    </span>
                ))}
            </div>
        </div>
    );
}

function Ring({ label, value, total }: { label: string; value: number; total: number }) {
    const percent = total === 0 ? 0 : Math.min(100, Math.round((value / total) * 100));
    const radius = 28;
    const circumference = 2 * Math.PI * radius;
    const dash = (percent / 100) * circumference;

    return (
        <div className="flex items-center gap-4">
            <svg viewBox="0 0 80 80" className="size-[72px] shrink-0" aria-hidden="true">
                <circle cx="40" cy="40" r={radius} className="fill-none stroke-zinc-100" strokeWidth="8" />
                <circle cx="40" cy="40" r={radius} className="fill-none stroke-blue-600" strokeWidth="8" strokeLinecap="round" strokeDasharray={`${dash} ${circumference - dash}`} transform="rotate(-90 40 40)" />
                <text x="40" y="44" textAnchor="middle" className="fill-zinc-900 text-[13px] font-semibold tabular-nums">{percent}%</text>
            </svg>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-zinc-900">{label}</p>
                <p className="text-xs text-zinc-500 tabular-nums">{value}/{total}</p>
            </div>
        </div>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
