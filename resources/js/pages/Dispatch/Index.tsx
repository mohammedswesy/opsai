import { router } from '@inertiajs/react';
import type { AxiosError } from 'axios';
import { ChevronDown, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { EmptyState } from '@/components/EmptyState';
import { controlClassName } from '@/components/FormField';
import { Avatar } from '@/components/Avatar';
import { PageHeader } from '@/components/PageHeader';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordList } from '@/components/RecordList';
import { StatusBadge } from '@/components/StatusBadge';
import { TableRow } from '@/components/Table';
import { useI18n } from '@/i18n';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { SKILLS } from '@/lib/options';
import { useCan } from '@/lib/permissions';
import AppLayout from '@/layouts/AppLayout';

type Job = {
    id: string;
    reference: string;
    title: string | null;
    start: string | null;
    end: string | null;
    status: string;
    priority: string | null;
    category: string | null;
    customer_name: string | null;
    technician_id: string | null;
    service_request_id?: string | null;
    work_order_id?: string | null;
    appointment_id?: string | null;
    customer_id?: string | null;
    branch_id?: string | null;
};

type Unassigned = {
    id: string;
    type: 'request' | 'work_order';
    reference: string;
    title: string;
    priority: string;
    category: string | null;
    requested_at: string | null;
    area: string | null;
    customer_name: string | null;
    customer_id: string;
    service_request_id: string;
    branch_id: string | null;
    estimated_minutes: number | null;
};

type TechnicianColumn = {
    id: string;
    name: string | null;
    role: string | null;
    skills: string[];
    availability: string;
    branch: string | null;
    booked_minutes: number;
    utilization: number;
    appointments: Job[];
    work_orders: Job[];
};

type Hours = {
    work_start: string;
    work_end: string;
    working_minutes: number;
    grid_start: string;
    grid_end: string;
    grid_padding_minutes: number;
};

type Board = {
    date: string;
    timezone: string;
    now: string;
    is_today: boolean;
    working_minutes: number;
    hours: Hours;
    nearest_day: string | null;
    filters: { date: string; branch_id: string | null };
    branches: { id: string; name: string }[];
    can_create_appointment: boolean;
    can_create_work_order: boolean;
    counts: { total: number; completed: number; unassigned: number; overdue: number };
    technicians: TechnicianColumn[];
    unassigned: Unassigned[];
};

type DragState = {
    job: Job;
    duration: number;
    technicianId: string;
    start: number;
    moved: boolean;
    pointerId: number;
    originX: number;
    originY: number;
};

const surface = 'rounded-card border border-line bg-surface shadow-card';

const accents: Record<string, string> = {
    hvac: 'border-s-info',
    electrical: 'border-s-warning',
    plumbing: 'border-s-brand',
    cleaning: 'border-s-success',
    pest_control: 'border-s-danger',
};

const tints: Record<string, string> = {
    completed: 'bg-success-soft',
    cancelled: 'bg-danger-soft',
    no_show: 'bg-danger-soft',
    in_progress: 'bg-info-soft',
    on_the_way: 'bg-info-soft',
    arrived: 'bg-info-soft',
    scheduled: 'bg-brand-soft',
    assigned: 'bg-brand-soft',
};

function clockMinutes(value: string | null): number | null {
    const match = value?.match(/T(\d{2}):(\d{2})/) ?? value?.match(/^(\d{2}):(\d{2})$/);

    if (!match) {
        return null;
    }

    return Number(match[1]) * 60 + Number(match[2]);
}

function clockLabel(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

function shiftDate(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);
    const next = new Date(Date.UTC(year, (month ?? 1) - 1, (day ?? 1) + days));

    return next.toISOString().slice(0, 10);
}

function skillKey(category: string | null): string | null {
    if (!category) {
        return null;
    }

    const key = category.trim().toLowerCase().replace(/\s+/g, '_');

    return (SKILLS as readonly string[]).includes(key) ? key : null;
}

function matchesSkill(category: string | null, skills: string[]): boolean {
    const key = skillKey(category);

    return key === null || skills.includes(key);
}

function snap(minutes: number, gridStart: number, gridEnd: number, duration: number): number {
    const snapped = Math.round(minutes / 15) * 15;
    const latest = Math.max(gridStart, gridEnd - duration);

    return Math.min(Math.max(snapped, gridStart), latest);
}

function freeSlots(technician: TechnicianColumn, minutes: number, workStart: number, workEnd: number): { start: number; end: number }[] {
    const needed = minutes > 0 ? minutes : 60;
    const busy = technician.appointments
        .filter((job) => job.status !== 'cancelled')
        .map((job) => ({ start: clockMinutes(job.start), end: clockMinutes(job.end) }))
        .filter((job): job is { start: number; end: number } => job.start !== null && job.end !== null && job.end > workStart && job.start < workEnd)
        .sort((left, right) => left.start - right.start);
    const slots: { start: number; end: number }[] = [];
    let cursor = workStart;

    busy.forEach((job) => {
        const start = Math.max(job.start, workStart);

        if (start - cursor >= needed) {
            slots.push({ start: cursor, end: cursor + needed });
        }

        cursor = Math.max(cursor, Math.min(job.end, workEnd));
    });

    if (workEnd - cursor >= needed) {
        slots.push({ start: cursor, end: cursor + needed });
    }

    return slots;
}

function validationMessage(error: unknown): string | null {
    const errors = (error as AxiosError<{ errors?: Record<string, string[]> }>).response?.data?.errors;
    const first = errors ? Object.values(errors)[0] : undefined;

    return Array.isArray(first) ? (first[0] ?? null) : null;
}

const ringTones = { brand: 'stroke-brand', success: 'stroke-success', warning: 'stroke-warning', danger: 'stroke-danger' };

function Ring({ label, value, total, tone }: { label: string; value: number; total: number; tone: keyof typeof ringTones }) {
    const format = useFormat();
    const percent = total === 0 ? 0 : Math.min(100, Math.round((value / total) * 100));
    const radius = 28;
    const circumference = 2 * Math.PI * radius;
    const dash = (percent / 100) * circumference;

    return (
        <div className="flex items-center gap-3">
            <svg viewBox="0 0 72 72" className="size-16 shrink-0" aria-hidden="true">
                <circle cx="36" cy="36" r={radius} className="fill-none stroke-surface-muted" strokeWidth="6" />
                {dash > 0 ? <circle cx="36" cy="36" r={radius} className={`fill-none ${ringTones[tone]}`} strokeWidth="6" strokeLinecap="round" strokeDasharray={`${dash} ${circumference - dash}`} transform="rotate(-90 36 36)" /> : null}
                <text x="36" y="40" textAnchor="middle" className="fill-ink text-micro font-semibold tabular-nums">
                    {format.number(percent)}%
                </text>
            </svg>
            <div className="min-w-0">
                <p className="text-body font-medium text-ink">{label}</p>
                <p className="text-caption text-ink-muted tabular-nums">
                    {format.number(value)}/{format.number(total)}
                </p>
            </div>
        </div>
    );
}

/** Utilisation bar capped at 100%; above that it turns to the warning tone. */
function UtilizationBar({ value, className = '' }: { value: number; className?: string }) {
    return (
        <span className={`block h-1.5 overflow-hidden rounded-badge bg-surface-muted ${className}`.trim()} aria-hidden="true">
            <span className={`block h-full rounded-badge ${value > 100 ? 'bg-warning' : 'bg-brand'}`} style={{ width: `${Math.min(Math.max(value, 0), 100)}%` }} />
        </span>
    );
}

export default function Index({ board }: { board: Board }) {
    const { t, dir } = useI18n();
    const can = useCan();
    const format = useFormat();
    const summaryId = useId();
    const gridRef = useRef<HTMLDivElement>(null);
    const [summaryOpen, setSummaryOpen] = useState(false);
    const categoryLabel = (category: string): string => {
        const key = skillKey(category);

        return key ? optionLabel(t.labels, key) : category;
    };
    const [skill, setSkill] = useState('');
    const [search, setSearch] = useState('');
    const [unassignedJob, setUnassignedJob] = useState<Unassigned | null>(null);
    const [selected, setSelected] = useState<Job | null>(null);
    const [draftStart, setDraftStart] = useState('');
    const [draftEnd, setDraftEnd] = useState('');
    const [draftTechnician, setDraftTechnician] = useState('');
    const [toast, setToast] = useState<string | null>(null);
    const [drag, setDrag] = useState<DragState | null>(null);
    const skipClick = useRef(false);
    const gridStart = clockMinutes(board.hours.grid_start) ?? 7 * 60;
    const gridEnd = clockMinutes(board.hours.grid_end) ?? 17 * 60;
    const workStart = clockMinutes(board.hours.work_start) ?? 8 * 60;
    const workEnd = clockMinutes(board.hours.work_end) ?? 16 * 60;
    const hours = Array.from({ length: (gridEnd - gridStart) / 60 }, (_, index) => gridStart + index * 60);
    const span = gridEnd - gridStart;
    const query = search.trim().toLowerCase();
    const technicians = useMemo(
        () =>
            board.technicians.filter((technician) => {
                const skillMatch = skill === '' || technician.skills.includes(skill);
                const haystack = [technician.name, technician.branch, ...technician.appointments.flatMap((job) => [job.title, job.customer_name, job.reference]), ...technician.work_orders.flatMap((job) => [job.title, job.customer_name, job.reference])].join(' ').toLowerCase();

                return skillMatch && (query === '' || haystack.includes(query));
            }),
        [board.technicians, query, skill],
    );
    const unassigned = board.unassigned.filter((job) => {
        const haystack = [job.title, job.area, job.customer_name, job.reference, job.category].join(' ').toLowerCase();

        return (skill === '' || skillKey(job.category) === skill || skillKey(job.category) === null) && (query === '' || haystack.includes(query));
    });
    const nowMinutes = clockMinutes(board.now);
    const canSchedule = board.can_create_appointment && can('appointments.manage');
    const canAssign = board.can_create_work_order && can('work_orders.manage');
    const dayEmpty = board.counts.total === 0;
    const filteredEmpty = technicians.length === 0 && unassigned.length === 0 && !dayEmpty;
    const ranked = unassignedJob ? rankTechnicians(technicians, unassignedJob, workStart, workEnd) : [];

    // Start the timeline at the start edge (the right in RTL), where the sticky hour column sits.
    useLayoutEffect(() => {
        if (gridRef.current) {
            gridRef.current.scrollLeft = 0;
        }
    }, [board.date, dir, technicians.length]);

    function visit(date: string, branchId = board.filters.branch_id) {
        router.get('/dispatch', { date, branch_id: branchId ?? '' }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function showError(errors: Record<string, string> | undefined, fallback: string) {
        const message = errors ? Object.values(errors).find((value) => typeof value === 'string') : undefined;
        setToast(message ?? fallback);
    }

    function schedule(job: Unassigned, technicianId: string, slot: { start: number; end: number }) {
        router.post(
            '/appointments',
            {
                customer_id: job.customer_id,
                technician_id: technicianId,
                service_request_id: job.service_request_id,
                branch_id: job.branch_id,
                scheduled_start: `${board.date}T${clockLabel(slot.start)}`,
                scheduled_end: `${board.date}T${clockLabel(slot.end)}`,
                status: 'scheduled',
            },
            {
                preserveScroll: true,
                onError: (errors) => showError(errors, t.common.error),
            },
        );
    }

    function assign(job: Unassigned, technicianId: string) {
        router.post(
            `/work-orders/${job.id}/assign`,
            { technician_id: technicianId },
            {
                preserveScroll: true,
                onError: (errors) => showError(errors, t.common.error),
                onSuccess: () => setUnassignedJob(null),
            },
        );
    }

    async function reschedule(job: Job, technicianId: string, start: number, end: number) {
        if (!job.customer_id || end <= start) {
            setToast(t.common.error);
            return;
        }

        try {
            await window.axios.put(`/appointments/${job.id}`, {
                customer_id: job.customer_id,
                technician_id: technicianId,
                service_request_id: job.service_request_id,
                branch_id: job.branch_id,
                scheduled_start: `${board.date}T${clockLabel(start)}`,
                scheduled_end: `${board.date}T${clockLabel(end)}`,
                status: job.status,
            });
            setSelected(null);
            router.get('/dispatch', { date: board.date, branch_id: board.filters.branch_id ?? '' }, { preserveScroll: true, replace: true });
        } catch (error) {
            setToast(validationMessage(error) ?? t.common.error);
        }
    }

    function openJob(job: Job) {
        setSelected(job);
        setDraftStart(job.start?.slice(11, 16) ?? '');
        setDraftEnd(job.end?.slice(11, 16) ?? '');
        setDraftTechnician(job.technician_id ?? '');
    }

    function beginDrag(event: ReactPointerEvent<HTMLButtonElement>, job: Job, technicianId: string) {
        if (!canSchedule || event.button !== 0) {
            return;
        }

        const start = clockMinutes(job.start);
        const end = clockMinutes(job.end);

        if (start === null || end === null || end <= start) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        setDrag({
            job,
            duration: end - start,
            technicianId,
            start,
            moved: false,
            pointerId: event.pointerId,
            originX: event.clientX,
            originY: event.clientY,
        });
    }

    function moveDrag(event: ReactPointerEvent<HTMLButtonElement>) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }

        const moved = drag.moved || Math.abs(event.clientX - drag.originX) > 4 || Math.abs(event.clientY - drag.originY) > 4;
        const lane = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-lane]');
        const technicianId = lane?.getAttribute('data-technician-id') ?? drag.technicianId;
        const rect = lane?.getBoundingClientRect();
        let start = drag.start;

        if (rect && rect.height > 0) {
            const raw = gridStart + ((event.clientY - rect.top) / rect.height) * span;
            start = snap(raw, gridStart, gridEnd, drag.duration);
        }

        setDrag({ ...drag, moved, technicianId, start });
    }

    function endDrag(event: ReactPointerEvent<HTMLButtonElement>) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }

        const current = drag;
        setDrag(null);

        if (!current.moved) {
            return;
        }

        skipClick.current = true;
        void reschedule(current.job, current.technicianId, current.start, current.start + current.duration);
    }

    function blocks(technician: TechnicianColumn): { job: Job; kind: 'appointment' | 'work_order' }[] {
        const appointmentIds = new Set(technician.appointments.map((job) => job.id));

        return [
            ...technician.appointments.map((job) => ({ job, kind: 'appointment' as const })),
            ...technician.work_orders
                .filter((job) => job.appointment_id == null || !appointmentIds.has(job.appointment_id))
                .map((job) => ({ job, kind: 'work_order' as const })),
        ];
    }

    function blockClass(job: Job): string {
        const accent = accents[skillKey(job.category) ?? ''] ?? 'border-s-brand';
        const tint = tints[job.status] ?? 'bg-surface';

        return `border border-line shadow-control ${tint} border-s-4 ${accent}`;
    }

    const countItems = [
        { key: 'total', label: t.dispatch.total, value: board.counts.total, tone: 'text-ink' },
        { key: 'completed', label: t.dispatch.completed, value: board.counts.completed, tone: 'text-success' },
        { key: 'unassigned', label: t.dispatch.unassigned, value: board.counts.unassigned, tone: board.counts.unassigned > 0 ? 'text-warning' : 'text-ink' },
        { key: 'overdue', label: t.dispatch.overdue, value: board.counts.overdue, tone: board.counts.overdue > 0 ? 'text-danger' : 'text-ink' },
    ];

    return (
        <div data-testid="dispatch-board">
            <PageHeader title={t.dispatch.title} subtitle={t.dispatch.subtitle} />
            {toast ? (
                <div className="mb-4 flex items-start justify-between gap-3 rounded-card border border-danger-border bg-danger-soft px-4 py-3 text-body text-danger" role="alert">
                    <p className="min-w-0 break-words">{toast}</p>
                    <button type="button" className="shrink-0 rounded-control p-1 hover:bg-danger-border" aria-label={t.dispatch.dismiss} onClick={() => setToast(null)}>
                        <X className="size-4" strokeWidth={1.75} aria-hidden="true" />
                    </button>
                </div>
            ) : null}

            <div className={`${surface} mb-4 flex flex-wrap items-end gap-3 p-4`}>
                <div className="flex items-center gap-1">
                    <Button variant="secondary" aria-label={t.dispatch.previousDay} onClick={() => visit(shiftDate(board.date, -1))}>
                        <ChevronLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                    </Button>
                    <input className={`${controlClassName} w-40`} type="date" aria-label={t.ops.date} value={board.date} onChange={(event) => visit(event.target.value)} />
                    <Button variant="secondary" aria-label={t.dispatch.nextDay} onClick={() => visit(shiftDate(board.date, 1))}>
                        <ChevronRight className="size-4 rtl:rotate-180" aria-hidden="true" />
                    </Button>
                </div>
                <div className="min-w-0 pb-0.5">
                    <p className="text-body font-medium text-ink">{format.date(board.date, 'long')}</p>
                    <p className="text-caption text-ink-muted tabular-nums">
                        {format.time(board.hours.work_start)}–{format.time(board.hours.work_end)}
                    </p>
                </div>
                <select className={`${controlClassName} max-w-xs`} aria-label={t.ops.branch} value={board.filters.branch_id ?? ''} onChange={(event) => visit(board.date, event.target.value)}>
                    <option value="">{t.dashboard.allBranches}</option>
                    {board.branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
                <select className={`${controlClassName} max-w-xs`} aria-label={t.dispatch.skill} value={skill} onChange={(event) => setSkill(event.target.value)}>
                    <option value="">{t.dispatch.skill}</option>
                    {SKILLS.map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <input className={`${controlClassName} max-w-xs`} value={search} placeholder={t.dispatch.search} aria-label={t.dispatch.search} onChange={(event) => setSearch(event.target.value)} />
            </div>

            <section className={`${surface} mb-4`}>
                <div className="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                    <h2 className="text-heading font-semibold text-ink">{t.dispatch.overview}</h2>
                    <dl className="flex flex-1 flex-wrap gap-x-5 gap-y-1 text-body">
                        {countItems.map((item) => (
                            <div key={item.key} className="flex items-baseline gap-1.5">
                                <dt className="text-ink-muted">{item.label}</dt>
                                <dd className={`font-semibold tabular-nums ${item.tone}`}>{format.number(item.value)}</dd>
                            </div>
                        ))}
                    </dl>
                    <button
                        type="button"
                        className="inline-flex min-h-9 items-center gap-1.5 rounded-control px-2.5 text-body font-medium text-brand hover:bg-surface-muted hover:text-brand-strong"
                        aria-expanded={summaryOpen}
                        aria-controls={summaryId}
                        onClick={() => setSummaryOpen((current) => !current)}
                    >
                        {summaryOpen ? t.dispatch.hideDetails : t.dispatch.showDetails}
                        <ChevronDown className={`size-4 transition-transform duration-200 ${summaryOpen ? 'rotate-180' : ''}`} strokeWidth={1.75} aria-hidden="true" />
                    </button>
                </div>
                {summaryOpen ? (
                    <div id={summaryId} className="grid gap-4 border-t border-line p-4 xl:grid-cols-[minmax(0,1fr)_16rem]">
                        <div className="min-w-0 overflow-x-auto">
                            <h3 className="mb-2 text-caption font-semibold text-ink-secondary">{t.dispatch.technicians}</h3>
                            <table className="min-w-full text-body">
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-3 py-2 text-start font-medium">{t.ops.technician}</th>
                                        <th scope="col" className="px-3 py-2 text-start font-medium">{t.ops.skills}</th>
                                        <th scope="col" className="px-3 py-2 text-start font-medium">{t.ops.availability}</th>
                                        <th scope="col" className="px-3 py-2 text-end font-medium">{t.dispatch.utilization}</th>
                                    </tr>
                                </thead>
                                <tbody className="text-ink-secondary">
                                    {technicians.map((technician) => (
                                        <TableRow key={`${technician.id}-row`}>
                                            <td className="px-3 py-2.5 font-medium text-ink">
                                                <bdi>{technician.name ?? t.common.none}</bdi>
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="flex flex-wrap gap-1">
                                                    {technician.skills.length === 0
                                                        ? t.common.none
                                                        : technician.skills.map((item) => (
                                                              <Chip key={item} tone="brand">
                                                                  {optionLabel(t.labels, item)}
                                                              </Chip>
                                                          ))}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <StatusBadge value={technician.availability} />
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="flex items-center justify-end gap-2" title={technician.utilization > 100 ? t.dispatch.overCapacity : undefined}>
                                                    <span className={`tabular-nums ${technician.utilization > 100 ? 'font-medium text-warning' : ''}`}>{format.number(technician.utilization)}%</span>
                                                    <UtilizationBar value={technician.utilization} className="w-16" />
                                                </div>
                                            </td>
                                        </TableRow>
                                    ))}
                                </tbody>
                            </table>
                            <p className="mt-2 text-caption text-ink-muted">
                                {t.dispatch.workingDay.replace('{start}', format.time(board.hours.work_start)).replace('{end}', format.time(board.hours.work_end))}
                            </p>
                        </div>
                        <div className="space-y-4">
                            <Ring label={t.dispatch.completed} value={board.counts.completed} total={board.counts.total} tone="success" />
                            <Ring label={t.dispatch.unassigned} value={board.counts.unassigned} total={board.counts.total} tone="warning" />
                            <Ring label={t.dispatch.overdue} value={board.counts.overdue} total={board.counts.total} tone="danger" />
                        </div>
                    </div>
                ) : null}
            </section>

            <RecordList empty={filteredEmpty} filtered>
                <div className="grid items-start gap-4 xl:grid-cols-[18rem_minmax(0,1fr)]">
                    <section className={`${surface} p-4`}>
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <h2 className="text-heading font-semibold text-ink">{t.dispatch.unassigned}</h2>
                            <span className="rounded-badge bg-brand-soft px-2 py-0.5 text-caption font-semibold text-brand-strong tabular-nums">{format.number(unassigned.length)}</span>
                        </div>
                        {unassigned.length === 0 ? (
                            <p className="text-body text-ink-muted">{t.dispatch.emptyUnassigned}</p>
                        ) : (
                            <div className="space-y-2">
                                {unassigned.map((job) => (
                                    <button
                                        key={job.id}
                                        type="button"
                                        className="w-full rounded-control border border-line bg-surface p-3 text-start shadow-control transition-colors hover:border-line-strong hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                                        onClick={() => setUnassignedJob(job)}
                                    >
                                        <p className="text-body font-medium break-words text-ink">
                                            <bdi>{job.title}</bdi>
                                        </p>
                                        <p className="mt-1 font-mono text-caption text-ink-muted">
                                            <bdi dir="ltr">{job.reference}</bdi>
                                        </p>
                                        <p className="mt-1 text-caption text-ink-muted">
                                            {job.requested_at ? format.time(job.requested_at) : t.common.none}
                                            {job.area ? (
                                                <>
                                                    {' · '}
                                                    <bdi>{job.area}</bdi>
                                                </>
                                            ) : null}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            <PriorityBadge value={job.priority} />
                                            {job.category ? <Chip tone="brand">{categoryLabel(job.category)}</Chip> : null}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </section>
                    <div className="min-w-0">
                        {dayEmpty ? (
                            <div className="space-y-3">
                                <EmptyState title={t.dispatch.emptyDay} description={t.dispatch.emptyDayHint} />
                                {board.nearest_day ? (
                                    <div className="flex justify-center">
                                        <Button variant="secondary" onClick={() => visit(board.nearest_day ?? board.date)}>
                                            {t.dispatch.nearestDay}: {format.date(board.nearest_day)}
                                        </Button>
                                    </div>
                                ) : null}
                            </div>
                        ) : technicians.length === 0 ? (
                            <EmptyState title={t.dispatch.emptyTechnicians} />
                        ) : (
                            <>
                                <div className="space-y-3 lg:hidden">
                                    {technicians.map((technician) => (
                                        <section key={technician.id} className={`${surface} p-4`}>
                                            <TechnicianHeading technician={technician} roleLabel={technician.role ? optionLabel(t.labels, technician.role) : null} />
                                            <div className="mt-3 space-y-2">
                                                {blocks(technician).map(({ job }) => (
                                                    <button key={`${job.id}-agenda`} type="button" className={`w-full rounded-control px-3 py-2 text-start ${blockClass(job)}`} onClick={() => openJob(job)}>
                                                        <p className="text-body font-medium break-words text-ink">
                                                            <bdi>{job.title}</bdi>
                                                        </p>
                                                        <p className="font-mono text-micro text-ink-muted">
                                                            <bdi dir="ltr">{job.reference}</bdi>
                                                        </p>
                                                        <p className="text-caption text-ink-muted tabular-nums">
                                                            {format.time(job.start)} – {format.time(job.end)}
                                                        </p>
                                                        <p className="truncate text-caption text-ink-muted">
                                                            <bdi>{job.customer_name}</bdi>
                                                        </p>
                                                        <StatusBadge value={job.status} />
                                                    </button>
                                                ))}
                                            </div>
                                        </section>
                                    ))}
                                </div>
                                <div ref={gridRef} className={`${surface} hidden overflow-x-auto lg:block`}>
                                    <div className="grid" style={{ gridTemplateColumns: `4.5rem repeat(${technicians.length}, minmax(10.5rem, 1fr))`, minWidth: `${4.5 + technicians.length * 10.5}rem` }}>
                                        <div className="sticky start-0 z-20 border-e border-b border-line bg-surface" />
                                        {technicians.map((technician) => (
                                            <div key={technician.id} className="border-e border-b border-line p-3 last:border-e-0">
                                                <TechnicianHeading technician={technician} roleLabel={technician.role ? optionLabel(t.labels, technician.role) : null} />
                                            </div>
                                        ))}
                                        <div className="sticky start-0 z-20 border-e border-line bg-surface">
                                            {hours.map((hour) => (
                                                <div key={hour} className="h-[3.25rem] border-t border-line px-2 pt-1 text-micro font-medium text-ink-secondary tabular-nums first:border-t-0">
                                                    {format.time(clockLabel(hour))}
                                                </div>
                                            ))}
                                        </div>
                                        {technicians.map((technician) => (
                                            <div key={`${technician.id}-lane`} data-lane data-technician-id={technician.id} className="relative border-e border-line last:border-e-0" style={{ height: `${hours.length * 3.25}rem` }}>
                                                {hours.map((hour) => (
                                                    <div
                                                        key={hour}
                                                        className={`absolute inset-x-0 border-t border-line first:border-t-0 ${hour < workStart || hour >= workEnd ? 'bg-surface-muted' : ''}`}
                                                        style={{ top: `${((hour - gridStart) / span) * 100}%`, height: `${(60 / span) * 100}%` }}
                                                    />
                                                ))}
                                                {board.is_today && nowMinutes !== null && nowMinutes >= gridStart && nowMinutes <= gridEnd ? (
                                                    <div className="absolute inset-x-0 z-10 border-t-2 border-danger" style={{ top: `${((nowMinutes - gridStart) / span) * 100}%` }} />
                                                ) : null}
                                                {drag?.technicianId === technician.id ? (
                                                    <div
                                                        className="pointer-events-none absolute inset-x-1 z-20 rounded-control border border-dashed border-brand bg-brand-soft px-2 py-1 text-caption shadow-control"
                                                        style={{ top: `${((drag.start - gridStart) / span) * 100}%`, height: `${(Math.max(drag.duration, 20) / span) * 100}%` }}
                                                    >
                                                        <span className="block truncate font-medium text-ink">{drag.job.title}</span>
                                                        <span className="block text-ink-muted tabular-nums">
                                                            {format.time(clockLabel(drag.start))}–{format.time(clockLabel(drag.start + drag.duration))}
                                                        </span>
                                                    </div>
                                                ) : null}
                                                {blocks(technician).map(({ job, kind }) => {
                                                    const start = clockMinutes(job.start);
                                                    const end = clockMinutes(job.end);

                                                    if (start === null || end === null || end <= start) {
                                                        return null;
                                                    }

                                                    const draggable = canSchedule && kind === 'appointment';

                                                    return (
                                                        <button
                                                            key={job.id}
                                                            type="button"
                                                            className={`absolute inset-x-1 z-10 flex flex-col items-start overflow-hidden rounded-control px-2 py-1 text-start text-caption leading-tight transition hover:brightness-95 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none ${blockClass(job)} ${drag?.job.id === job.id ? 'opacity-40' : ''} ${draggable ? 'cursor-grab' : ''}`}
                                                            style={{
                                                                top: `${((start - gridStart) / span) * 100}%`,
                                                                height: `${(Math.max(end - start, 20) / span) * 100}%`,
                                                            }}
                                                            onClick={() => {
                                                                if (skipClick.current) {
                                                                    skipClick.current = false;
                                                                    return;
                                                                }

                                                                openJob(job);
                                                            }}
                                                            onPointerDown={draggable ? (event) => beginDrag(event, job, technician.id) : undefined}
                                                            onPointerMove={draggable ? moveDrag : undefined}
                                                            onPointerUp={draggable ? endDrag : undefined}
                                                        >
                                                            <span className="block w-full truncate font-medium text-ink">{job.title}</span>
                                                            <span className="block w-full truncate text-ink-muted tabular-nums">
                                                                {format.time(job.start)}–{format.time(job.end)}
                                                            </span>
                                                            <span className="block w-full truncate text-ink-muted">{job.customer_name}</span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        ))}
                                    </div>
                                    <p className="border-t border-line px-4 py-2 text-caption text-ink-muted">
                                        {t.dispatch.gridNote} {technicians.length > 4 ? t.dispatch.scrollHint : null}
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </RecordList>

            {unassignedJob ? (
                <Drawer title={unassignedJob.type === 'work_order' ? t.dispatch.assign : t.dispatch.schedule} onClose={() => setUnassignedJob(null)} closeLabel={t.dispatch.close}>
                    <p className="text-body font-medium text-ink">
                        <bdi>{unassignedJob.title}</bdi>
                    </p>
                    <p className="mt-1 font-mono text-caption text-ink-muted">
                        <bdi dir="ltr">{unassignedJob.reference}</bdi>
                    </p>
                    <p className="mt-3 text-caption text-ink-muted">{t.dispatch.suggestion}</p>
                    <div className="mt-4 space-y-3">
                        {ranked.map((item, index) => (
                            <div key={item.technician.id} className="rounded-control border border-line p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-body font-medium text-ink">
                                        <bdi>{item.technician.name}</bdi>
                                    </p>
                                    {index === 0 && item.slots.length > 0 ? <Chip tone="brand">{t.dispatch.bestMatch}</Chip> : null}
                                </div>
                                <div className="mt-1.5 flex items-center gap-2">
                                    <span className={`text-caption tabular-nums ${item.technician.utilization > 100 ? 'font-medium text-warning' : 'text-ink-muted'}`}>{format.number(item.technician.utilization)}%</span>
                                    <UtilizationBar value={item.technician.utilization} className="w-20" />
                                </div>
                                {unassignedJob.type === 'work_order' ? (
                                    <Button className="mt-2" variant="secondary" disabled={!canAssign} onClick={() => assign(unassignedJob, item.technician.id)}>
                                        {t.dispatch.assign}
                                    </Button>
                                ) : item.slots.length === 0 ? (
                                    <p className="mt-1 text-caption text-ink-muted">{t.dispatch.noSlots}</p>
                                ) : (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {item.slots.map((slot) => (
                                            <Button key={`${slot.start}-${slot.end}`} variant="secondary" disabled={!canSchedule} onClick={() => schedule(unassignedJob, item.technician.id, slot)}>
                                                <span className="tabular-nums">
                                                    {format.time(clockLabel(slot.start))}–{format.time(clockLabel(slot.end))}
                                                </span>
                                            </Button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                        {ranked.length === 0 ? <p className="text-body text-ink-muted">{t.dispatch.noMatch}</p> : null}
                    </div>
                </Drawer>
            ) : null}
            {selected ? (
                <Drawer title={t.dispatch.details} onClose={() => setSelected(null)} closeLabel={t.dispatch.close}>
                    <p className="text-body font-medium text-ink">
                        <bdi>{selected.title}</bdi>
                    </p>
                    <p className="mt-1 font-mono text-caption text-ink-muted">
                        <bdi dir="ltr">{selected.reference}</bdi>
                    </p>
                    <dl className="mt-3 space-y-2 text-body">
                        <div>
                            <dt className="text-caption text-ink-muted">{t.dispatch.customer}</dt>
                            <dd className="text-ink">
                                <bdi>{selected.customer_name ?? t.common.none}</bdi>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-caption text-ink-muted">{t.dispatch.time}</dt>
                            <dd className="text-ink tabular-nums">
                                {format.time(selected.start)} – {format.time(selected.end)}
                            </dd>
                        </div>
                        <div className="flex flex-wrap gap-1">
                            <StatusBadge value={selected.status} />
                            {selected.priority ? <PriorityBadge value={selected.priority} /> : null}
                            {selected.category ? <Chip tone="brand">{categoryLabel(selected.category)}</Chip> : null}
                        </div>
                    </dl>
                    {canSchedule && selected.appointment_id === undefined ? (
                        <form
                            className="mt-4 space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const start = snap(clockMinutes(draftStart) ?? gridStart, gridStart, gridEnd, 15);
                                const end = snap(clockMinutes(draftEnd) ?? start + 60, gridStart, gridEnd, 15);
                                void reschedule(selected, draftTechnician, start, end);
                            }}
                        >
                            <label className="block text-body">
                                <span className="text-caption text-ink-muted">{t.ops.technician}</span>
                                <select className={`${controlClassName} mt-1`} value={draftTechnician} onChange={(event) => setDraftTechnician(event.target.value)}>
                                    {board.technicians.map((technician) => (
                                        <option key={technician.id} value={technician.id}>
                                            {technician.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <label className="block text-body">
                                    <span className="text-caption text-ink-muted">{t.ops.start}</span>
                                    <input className={`${controlClassName} mt-1`} type="time" step={900} value={draftStart} onChange={(event) => setDraftStart(event.target.value)} />
                                </label>
                                <label className="block text-body">
                                    <span className="text-caption text-ink-muted">{t.ops.end}</span>
                                    <input className={`${controlClassName} mt-1`} type="time" step={900} value={draftEnd} onChange={(event) => setDraftEnd(event.target.value)} />
                                </label>
                            </div>
                            <Button type="submit" variant="primary">
                                {t.dispatch.reschedule}
                            </Button>
                        </form>
                    ) : null}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {selected.service_request_id && can('requests.view') ? (
                            <ButtonLink href={`/requests/${selected.service_request_id}`} variant="secondary">
                                {t.ops.relatedRequest}
                            </ButtonLink>
                        ) : null}
                        {selected.work_order_id ? (
                            <ButtonLink href={`/work-orders/${selected.work_order_id}`} variant="secondary">
                                {t.nav.workOrders}
                            </ButtonLink>
                        ) : null}
                        {selected.appointment_id !== undefined ? (
                            <ButtonLink href={`/work-orders/${selected.id}`} variant="secondary">
                                {t.nav.workOrders}
                            </ButtonLink>
                        ) : null}
                        {board.can_create_appointment && selected.appointment_id === undefined ? (
                            <ButtonLink href={`/appointments/${selected.id}/edit`} variant="primary">
                                {t.common.edit}
                            </ButtonLink>
                        ) : null}
                    </div>
                </Drawer>
            ) : null}
        </div>
    );
}

function rankTechnicians(list: TechnicianColumn[], job: Unassigned, workStart: number, workEnd: number) {
    return list
        .map((technician) => ({
            technician,
            slots: freeSlots(technician, job.estimated_minutes ?? 60, workStart, workEnd),
            matched: matchesSkill(job.category, technician.skills),
        }))
        .filter((item) => item.matched)
        .sort((left, right) => {
            if ((left.slots.length === 0) !== (right.slots.length === 0)) {
                return left.slots.length === 0 ? 1 : -1;
            }

            if (left.technician.utilization !== right.technician.utilization) {
                return left.technician.utilization - right.technician.utilization;
            }

            return (left.slots[0]?.start ?? 0) - (right.slots[0]?.start ?? 0);
        });
}

const availabilityDot: Record<string, string> = { available: 'bg-success', busy: 'bg-warning' };

function TechnicianHeading({ technician, roleLabel }: { technician: TechnicianColumn; roleLabel: string | null }) {
    const { t } = useI18n();
    const format = useFormat();
    const over = technician.utilization > 100;

    return (
        <div className="flex items-center gap-2">
            <span className="relative">
                <Avatar name={technician.name ?? ''} size="sm" />
                <span className={`absolute -end-0.5 -bottom-0.5 size-2.5 rounded-badge border-2 border-surface ${availabilityDot[technician.availability] ?? 'bg-ink-subtle'}`} aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-body font-medium text-ink">
                    <bdi>{technician.name}</bdi>
                </p>
                <p className="truncate text-caption text-ink-muted">
                    <bdi>{roleLabel ?? technician.branch}</bdi>
                </p>
                <div className="mt-1 flex items-center gap-1.5" title={over ? t.dispatch.overCapacity : undefined}>
                    <UtilizationBar value={technician.utilization} className="flex-1" />
                    <span className={`text-micro tabular-nums ${over ? 'font-semibold text-warning' : 'text-ink-muted'}`}>
                        {format.number(technician.utilization)}%<span className="sr-only"> {t.dispatch.utilization}</span>
                    </span>
                </div>
            </div>
        </div>
    );
}

/** Side sheet for scheduling and job details: a modal dialog that closes on Escape or backdrop click. */
function Drawer({ title, onClose, closeLabel, children }: { title: string; onClose: () => void; closeLabel: string; children: ReactNode }) {
    const titleId = useId();
    const panelRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const previous = document.activeElement as HTMLElement | null;
        panelRef.current?.querySelector<HTMLElement>('button')?.focus();

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            previous?.focus();
        };
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-40 flex justify-end bg-ink/40">
            <button type="button" tabIndex={-1} className="absolute inset-0" aria-label={closeLabel} onClick={onClose} />
            <aside ref={panelRef} role="dialog" aria-modal="true" aria-labelledby={titleId} className="relative z-10 h-full w-full max-w-md overflow-y-auto border-s border-line bg-surface p-5 shadow-overlay">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h2 id={titleId} className="text-heading font-semibold text-ink">
                        {title}
                    </h2>
                    <Button variant="secondary" onClick={onClose}>
                        {closeLabel}
                    </Button>
                </div>
                {children}
            </aside>
        </div>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
