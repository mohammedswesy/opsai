import { router, usePage } from '@inertiajs/react';
import { Building2, CalendarClock, CalendarDays, CircleDollarSign, ClipboardList, Columns3, Inbox, ListTodo, Plus, Receipt, UserCog, Users, UserX, Wallet } from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar } from '@/components/Avatar';
import { ButtonLink } from '@/components/Button';
import { DateInput } from '@/components/DateInput';
import { controlClassName } from '@/components/FormField';
import { KpiCard } from '@/components/KpiCard';
import { PageTitle } from '@/components/PageTitle';
import { ScheduleList, type ScheduleItem } from '@/components/ScheduleList';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { useNavHidden } from '@/lib/navigation';
import { useCan } from '@/lib/permissions';
import { fill } from '@/lib/text';
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
    /** Null when the viewer cannot assign work (same rule as the dispatch board). */
    unassigned_jobs?: number | null;
    /** Null when the viewer cannot see appointments; technicians get only their own. */
    overdue_appointments?: number | null;
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

type ChartRow = { key: string; date: string; value: number };

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
    upcoming_today = null,
    my_day = null,
}: {
    metrics: Metrics;
    finance: Finance | null;
    branches: Option[];
    filters: { branch_id?: string; from?: string; to?: string };
    upcoming_today?: ScheduleItem[] | null;
    my_day?: ScheduleItem[] | null;
}) {
    const { t, locale } = useI18n();
    const { auth } = usePage().props;
    const can = useCan();
    const navHidden = useNavHidden();
    const format = useFormat();
    const hour = new Date().getHours();
    const name = auth.user?.name ?? '';
    const today = format.date(new Date(), 'long');
    const waiting = metrics.open_requests + metrics.pending_tasks;
    const summary =
        waiting === 0 && metrics.todays_appointments === 0 && metrics.active_work_orders === 0
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
    // Shortcuts follow both permissions and the role's navigation, so nobody is sent to a section their sidebar hides.
    const canRequest = can('requests.manage') && !navHidden('/requests');
    const canDispatch = (can('appointments.view') || can('work_orders.view')) && !navHidden('/dispatch');
    const overdueTone = finance && Number(finance.overdue) > 0 ? 'danger' : 'success';
    const unassigned = metrics.unassigned_jobs ?? null;
    const overdueAppointments = metrics.overdue_appointments ?? null;
    // Overdue invoices leave the top row when overdue appointments take their place, and join the finance row.
    const overdueInvoicesInFinance = finance !== null && overdueAppointments !== null;

    function apply(next: { branch_id?: string; from?: string; to?: string }) {
        router.get('/', { ...filters, ...next }, { preserveState: true });
    }

    return (
        <div className="space-y-6">
            <PageTitle title={t.nav.dashboard} />
            <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                    <p className="text-caption font-medium text-ink-muted ltr:tracking-wide ltr:uppercase">{today}</p>
                    <h1 className="mt-1 text-title font-semibold tracking-tight break-words text-ink">
                        {t.dashboard[greetingKey(hour)]}
                        {locale === 'ar' ? '، ' : ', '}
                        <bdi>{name}</bdi>
                    </h1>
                    <p className="mt-2 max-w-2xl text-body text-ink-secondary">{summary}</p>
                </div>
                {canRequest || canDispatch ? (
                    <div className="flex flex-wrap gap-2">
                        {canRequest ? (
                            <ButtonLink href="/requests/create">
                                <Plus className="size-4" strokeWidth={1.75} aria-hidden="true" />
                                {t.dashboard.newRequest}
                            </ButtonLink>
                        ) : null}
                        {canDispatch ? (
                            <ButtonLink href="/dispatch" variant="secondary">
                                <Columns3 className="size-4" strokeWidth={1.75} aria-hidden="true" />
                                {t.dashboard.openDispatch}
                            </ButtonLink>
                        ) : null}
                    </div>
                ) : null}
            </header>

            {my_day !== null ? (
                <Section title={t.dashboard.myDay} description={t.dashboard.myDayHint} data-testid="my-day">
                    <ScheduleList items={my_day} empty={t.dashboard.myDayEmpty} />
                </Section>
            ) : null}

            <form className="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-card">
                <label className="block min-w-48 flex-1">
                    <span className="mb-1.5 block text-caption font-medium text-ink-muted">{t.dashboard.allBranches}</span>
                    <select className={controlClassName} name="branch_id" defaultValue={filters.branch_id ?? ''} onChange={(event) => apply({ branch_id: event.target.value })}>
                        <option value="">{t.dashboard.allBranches}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                </label>
                <DateField label={t.dashboard.from} value={filters.from ?? ''} placeholder={t.dashboard.pickDate} onChange={(from) => apply({ from })} />
                <DateField label={t.dashboard.to} value={filters.to ?? ''} placeholder={t.dashboard.pickDate} onChange={(to) => apply({ to })} />
            </form>

            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard icon={CalendarDays} tone="info" label={t.dashboard.todaysAppointments} value={metrics.todays_appointments} />
                {unassigned !== null ? (
                    <KpiCard icon={UserX} tone={unassigned > 0 ? 'warning' : 'success'} label={t.dashboard.unassignedJobs} value={unassigned} />
                ) : (
                    <KpiCard icon={Inbox} tone={metrics.open_requests > 0 ? 'warning' : 'success'} label={t.dashboard.openRequests} value={metrics.open_requests} />
                )}
                <KpiCard icon={ClipboardList} label={t.dashboard.activeWorkOrders} value={metrics.active_work_orders} />
                {overdueAppointments !== null ? (
                    <KpiCard icon={CalendarClock} tone={overdueAppointments > 0 ? 'danger' : 'success'} label={t.dashboard.overdueAppointments} value={overdueAppointments} />
                ) : finance ? (
                    <KpiCard icon={CircleDollarSign} tone={overdueTone} label={t.dashboard.overdueInvoices} value={format.money(finance.overdue, finance.currency)} />
                ) : (
                    <KpiCard icon={ListTodo} tone={metrics.pending_tasks > 0 ? 'warning' : 'success'} label={t.dashboard.pendingTasks} value={metrics.pending_tasks} />
                )}
            </section>

            {upcoming_today !== null && my_day === null ? (
                <Section
                    title={t.dashboard.upcomingToday}
                    data-testid="upcoming-today"
                    action={
                        canDispatch ? (
                            <TextLink href="/dispatch" className="text-body">
                                {t.dashboard.openDispatch}
                            </TextLink>
                        ) : null
                    }
                >
                    <ScheduleList items={upcoming_today} empty={t.dashboard.upcomingTodayEmpty} showTechnician />
                </Section>
            ) : null}

            <section className="grid gap-3 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <Section title={t.dashboard.todayOverview}>
                    <div className="space-y-5">
                        <Ring label={t.dashboard.completedWorkOrders} value={metrics.completed_work_orders} total={metrics.completed_work_orders + metrics.active_work_orders} tone="success" />
                        <Ring label={t.dashboard.activeWorkOrders} value={metrics.active_work_orders} total={metrics.completed_work_orders + metrics.active_work_orders} tone="brand" />
                    </div>
                </Section>
                <Section title={t.dashboard.technicianWorkload} description={t.dashboard.workloadHint}>
                    {metrics.technician_workload.length === 0 ? (
                        <p className="text-body text-ink-muted">{t.ops.empty}</p>
                    ) : (
                        <ul className="space-y-3">
                            {metrics.technician_workload.map((row) => (
                                <li key={row.name} className="flex items-center gap-3">
                                    <Avatar name={row.name} size="sm" />
                                    <span dir="auto" className="w-36 truncate text-body text-ink-secondary sm:w-44" title={row.name}>
                                        {row.name}
                                    </span>
                                    <span className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-badge bg-surface-muted" aria-hidden="true">
                                        <span className="block h-full rounded-badge bg-brand" style={{ width: `${row.count === 0 ? 0 : Math.max(4, (row.count / maxLoad) * 100)}%` }} />
                                    </span>
                                    <span className="w-8 text-end text-body font-medium text-ink tabular-nums">{format.number(row.count)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </section>

            <section className="grid gap-3 lg:grid-cols-3">
                <Section title={t.dashboard.requestsByDay} className="lg:col-span-2">
                    {requestsEmpty ? (
                        <p className="text-body text-ink-muted">{t.dashboard.emptyChart}</p>
                    ) : (
                        <ColumnChart
                            title={t.dashboard.requestsByDay}
                            tone="brand"
                            rows={metrics.requests_by_day.map((day) => ({ key: day.date, date: day.date, value: day.count }))}
                            formatValue={(value) => format.number(value)}
                        />
                    )}
                </Section>
                <Section title={t.dashboard.workOrdersByStatus}>
                    {ordersEmpty ? (
                        <p className="text-body text-ink-muted">{t.dashboard.emptyChart}</p>
                    ) : (
                        <StatusBars rows={metrics.work_orders_by_status} max={maxOrders} tone="brand" />
                    )}
                </Section>
            </section>

            {finance ? (
                <section className="space-y-3" data-testid="finance-metrics">
                    <h2 className="text-heading font-semibold text-ink">{t.dashboard.financeTitle}</h2>
                    <div className="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.8fr)]">
                        <Section title={t.dashboard.revenueByDay} action={<span className="text-body font-medium text-ink tabular-nums">{format.money(finance.revenue, finance.currency)}</span>}>
                            {financeDaysEmpty ? (
                                <p className="text-body text-ink-muted">{t.dashboard.emptyChart}</p>
                            ) : (
                                <ColumnChart
                                    title={t.dashboard.revenueByDay}
                                    tone="info"
                                    rows={finance.by_day.map((day) => ({ key: day.date, date: day.date, value: Number(day.revenue) }))}
                                    formatValue={(value) => format.money(value, finance.currency)}
                                />
                            )}
                        </Section>
                        <Section title={t.dashboard.invoiceStatus}>
                            {invoiceStatusEmpty ? <p className="text-body text-ink-muted">{t.dashboard.emptyChart}</p> : <StatusBars rows={finance.invoice_status} max={maxStatus} tone="info" />}
                        </Section>
                    </div>
                    <div className={`grid gap-3 sm:grid-cols-2 ${overdueInvoicesInFinance ? 'lg:grid-cols-3 xl:grid-cols-5' : 'xl:grid-cols-4'}`}>
                        {overdueInvoicesInFinance ? (
                            <KpiCard size="quiet" icon={CircleDollarSign} tone={overdueTone} label={t.dashboard.overdueInvoices} value={format.money(finance.overdue, finance.currency)} />
                        ) : null}
                        <KpiCard size="quiet" icon={Receipt} tone="warning" label={t.dashboard.outstanding} value={format.money(finance.outstanding, finance.currency)} />
                        <KpiCard size="quiet" icon={Receipt} label={t.dashboard.invoiced} value={format.money(finance.invoiced, finance.currency)} />
                        <KpiCard size="quiet" icon={Wallet} tone="success" label={t.dashboard.paid} value={format.money(finance.paid, finance.currency)} />
                        <KpiCard size="quiet" icon={Wallet} tone="info" label={t.dashboard.todaysPayments} value={format.money(finance.todays_payments, finance.currency)} />
                    </div>
                </section>
            ) : null}

            <section>
                <h2 className="mb-3 text-caption font-semibold text-ink-muted ltr:tracking-wide ltr:uppercase">{t.dashboard.secondary}</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {unassigned !== null ? <KpiCard size="quiet" icon={Inbox} tone="warning" label={t.dashboard.openRequests} value={metrics.open_requests} /> : null}
                    {finance ? <KpiCard size="quiet" icon={ListTodo} tone="warning" label={t.dashboard.pendingTasks} value={metrics.pending_tasks} /> : null}
                    <KpiCard size="quiet" icon={ClipboardList} tone="success" label={t.dashboard.completedWorkOrders} value={metrics.completed_work_orders} />
                    <KpiCard size="quiet" icon={UserCog} tone="success" label={t.dashboard.availableTechnicians} value={metrics.available_technicians} />
                    <KpiCard size="quiet" icon={Building2} label={t.dashboard.branches} value={metrics.branches} />
                    <KpiCard size="quiet" icon={Users} label={t.dashboard.members} value={metrics.members} />
                </div>
            </section>
        </div>
    );
}

function DateField({ label, value, placeholder, onChange }: { label: string; value: string; placeholder: string; onChange: (value: string) => void }) {
    return (
        <label className="block min-w-44">
            <span className="mb-1.5 block text-caption font-medium text-ink-muted">{label}</span>
            <DateInput aria-label={label} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} />
        </label>
    );
}

const barTones = { brand: 'bg-brand', info: 'bg-info', success: 'bg-success' };
const fillTones = { brand: 'fill-brand', info: 'fill-info' };

function StatusBars({ rows, max, tone }: { rows: { status: string; count: number }[]; max: number; tone: keyof typeof barTones }) {
    const format = useFormat();

    return (
        <ul className="space-y-3">
            {rows
                .filter((row) => row.count > 0)
                .map((row) => (
                    <li key={row.status}>
                        <div className="mb-1.5 flex items-center justify-between gap-3">
                            <StatusBadge value={row.status} />
                            <span className="text-caption font-medium text-ink-secondary tabular-nums">{format.number(row.count)}</span>
                        </div>
                        <span className="block h-1.5 overflow-hidden rounded-badge bg-surface-muted" aria-hidden="true">
                            <span className={`block h-full rounded-badge ${barTones[tone]}`} style={{ width: `${(row.count / max) * 100}%` }} />
                        </span>
                    </li>
                ))}
        </ul>
    );
}

/**
 * Daily column chart. Bars run in reading order (oldest at the start edge), so the SVG
 * is mirrored in RTL to stay aligned with the date labels underneath. Screen readers
 * get a one-line summary plus a hidden table of every day instead of the drawing.
 */
function ColumnChart({ title, rows, tone, formatValue }: { title: string; rows: ChartRow[]; tone: keyof typeof fillTones; formatValue: (value: number) => string }) {
    const { t, dir } = useI18n();
    const format = useFormat();
    const max = Math.max(1, ...rows.map((row) => row.value));
    const peak = rows.reduce((best, row) => (row.value > best.value ? row : best), rows[0]);
    const total = rows.reduce((sum, row) => sum + row.value, 0);
    const summary = fill(t.dashboard.chartSummary, { total: formatValue(total), peak: formatValue(peak.value), date: format.date(peak.date) });
    const slot = 100 / rows.length;

    return (
        <figure className="mt-1">
            <figcaption className="text-caption text-ink-muted">{summary}</figcaption>
            <svg viewBox="0 0 100 36" className="mt-3 h-36 w-full overflow-visible" aria-hidden="true" preserveAspectRatio="none">
                {[12, 24].map((line) => (
                    <line key={line} x1="0" x2="100" y1={line} y2={line} className="stroke-line" strokeWidth="0.35" />
                ))}
                <line x1="0" x2="100" y1="34" y2="34" className="stroke-line-strong" strokeWidth="0.35" />
                {rows.map((row, index) => {
                    if (row.value <= 0) {
                        return null;
                    }

                    const height = Math.max(1.6, (row.value / max) * 30);
                    const width = slot * 0.62;
                    const start = index * slot + (slot - width) / 2;
                    const x = dir === 'rtl' ? 100 - start - width : start;

                    return <rect key={row.key} x={x} y={34 - height} width={width} height={height} rx="1.1" className={fillTones[tone]} />;
                })}
            </svg>
            <div className="mt-2 flex" aria-hidden="true">
                {rows.map((row, index) => (
                    <span key={row.key} className="min-w-0 flex-1 truncate text-center text-micro text-ink-muted tabular-nums" title={`${format.date(row.date)} · ${formatValue(row.value)}`}>
                        {index % 2 === 0 ? format.date(row.date, 'short') : ''}
                    </span>
                ))}
            </div>
            <table className="sr-only">
                <caption>{title}</caption>
                <thead>
                    <tr>
                        <th scope="col">{t.dashboard.chartDate}</th>
                        <th scope="col">{t.dashboard.chartValue}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.key}>
                            <td>{format.date(row.date)}</td>
                            <td>{formatValue(row.value)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

const ringTones = { brand: 'stroke-brand', success: 'stroke-success' };

function Ring({ label, value, total, tone }: { label: string; value: number; total: number; tone: keyof typeof ringTones }) {
    const format = useFormat();
    const percent = total === 0 ? 0 : Math.min(100, Math.round((value / total) * 100));
    const radius = 28;
    const circumference = 2 * Math.PI * radius;
    const dash = (percent / 100) * circumference;

    return (
        <div className="flex items-center gap-4">
            <svg viewBox="0 0 80 80" className="size-[72px] shrink-0" aria-hidden="true">
                <circle cx="40" cy="40" r={radius} className="fill-none stroke-surface-muted" strokeWidth="8" />
                {dash > 0 ? <circle cx="40" cy="40" r={radius} className={`fill-none ${ringTones[tone]}`} strokeWidth="8" strokeLinecap="round" strokeDasharray={`${dash} ${circumference - dash}`} transform="rotate(-90 40 40)" /> : null}
                <text x="40" y="44" textAnchor="middle" className="fill-ink text-caption font-semibold tabular-nums">
                    {format.number(percent)}%
                </text>
            </svg>
            <div className="min-w-0">
                <p className="truncate text-body font-medium text-ink">{label}</p>
                <p className="text-caption text-ink-muted tabular-nums">
                    {format.number(value)}/{format.number(total)}
                </p>
            </div>
        </div>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
