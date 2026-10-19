import { router, usePage } from '@inertiajs/react';
import type { AxiosError } from 'axios';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { Card } from '@/components/Card';
import { Chip } from '@/components/Chip';
import { EmptyState } from '@/components/EmptyState';
import { controlClassName } from '@/components/FormField';
import { Initials } from '@/components/Initials';
import { PageHeader } from '@/components/PageHeader';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordList } from '@/components/RecordList';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
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

const SKILLS = ['hvac', 'electrical', 'plumbing', 'cleaning', 'pest_control'];

const accents: Record<string, string> = {
    hvac: 'border-s-sky-600',
    electrical: 'border-s-amber-500',
    plumbing: 'border-s-cyan-700',
    cleaning: 'border-s-green-600',
    pest_control: 'border-s-violet-600',
};

const tints: Record<string, string> = {
    completed: 'bg-green-50',
    cancelled: 'bg-red-50',
    no_show: 'bg-red-50',
    in_progress: 'bg-sky-50',
    on_the_way: 'bg-sky-50',
    arrived: 'bg-sky-50',
    scheduled: 'bg-teal-50/80',
    assigned: 'bg-teal-50/80',
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

    return SKILLS.includes(key) ? key : null;
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

function Ring({ label, value, total }: { label: string; value: number; total: number }) {
    const percent = total === 0 ? 0 : Math.min(100, Math.round((value / total) * 100));
    const radius = 28;
    const circumference = 2 * Math.PI * radius;
    const dash = (percent / 100) * circumference;

    return (
        <div className="flex items-center gap-3">
            <svg viewBox="0 0 72 72" className="size-16 shrink-0" aria-hidden="true">
                <circle cx="36" cy="36" r={radius} className="fill-none stroke-zinc-100" strokeWidth="6" />
                <circle
                    cx="36"
                    cy="36"
                    r={radius}
                    className="fill-none stroke-teal-700"
                    strokeWidth="6"
                    strokeLinecap="round"
                    strokeDasharray={`${dash} ${circumference - dash}`}
                    transform="rotate(-90 36 36)"
                />
                <text x="36" y="40" textAnchor="middle" className="fill-zinc-900 text-[11px] font-semibold">
                    {percent}%
                </text>
            </svg>
            <div>
                <p className="text-sm font-medium text-zinc-900">{label}</p>
                <p className="text-xs tabular-nums text-zinc-500">
                    {value}/{total}
                </p>
            </div>
        </div>
    );
}

export default function Index({ board }: { board: Board }) {
    const { t } = useI18n();
    const permissions = usePage().props.permissions;
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
    const canSchedule = board.can_create_appointment && (permissions.includes('*') || permissions.includes('appointments.manage'));
    const canAssign = board.can_create_work_order && (permissions.includes('*') || permissions.includes('work_orders.manage'));
    const dayEmpty = board.counts.total === 0;
    const filteredEmpty = technicians.length === 0 && unassigned.length === 0 && !dayEmpty;
    const ranked = unassignedJob ? rankTechnicians(technicians, unassignedJob, workStart, workEnd) : [];

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
        const accent = accents[skillKey(job.category) ?? ''] ?? 'border-s-teal-700';
        const tint = tints[job.status] ?? 'bg-white';

        return `border border-zinc-200/80 shadow-sm ${tint} border-s-4 ${accent}`;
    }

    return (
        <div data-testid="dispatch-board">
            <PageHeader title={t.dispatch.title} subtitle={t.dispatch.subtitle} />
            {toast ? (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm" role="status">
                    {toast}
                </div>
            ) : null}
            <Card className="mb-4 shadow-sm">
                <div className="flex flex-wrap items-end gap-3 p-4">
                    <div className="flex items-center gap-1">
                        <Button variant="secondary" aria-label={t.dispatch.previousDay} onClick={() => visit(shiftDate(board.date, -1))}>
                            <ChevronLeft className="size-4 rtl:rotate-180" />
                        </Button>
                        <input className={`${controlClassName} w-40`} type="date" aria-label={t.ops.date} value={board.date} onChange={(event) => visit(event.target.value)} />
                        <Button variant="secondary" aria-label={t.dispatch.nextDay} onClick={() => visit(shiftDate(board.date, 1))}>
                            <ChevronRight className="size-4 rtl:rotate-180" />
                        </Button>
                    </div>
                    <p className="pb-2 text-xs tabular-nums text-zinc-500">
                        {board.hours.work_start}–{board.hours.work_end}
                    </p>
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
            </Card>
            <RecordList empty={filteredEmpty}>
                <div className="grid items-start gap-4 xl:grid-cols-[18rem_minmax(0,1fr)]">
                    <Card className="p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold text-zinc-900">{t.dispatch.unassigned}</h2>
                            <span className="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-800 tabular-nums">{unassigned.length}</span>
                        </div>
                        {unassigned.length === 0 ? (
                            <p className="text-sm text-zinc-500">{t.dispatch.emptyUnassigned}</p>
                        ) : (
                            <div className="space-y-2">
                                {unassigned.map((job) => (
                                    <button key={job.id} type="button" className="w-full rounded-xl border border-zinc-200 bg-white p-3 text-start shadow-sm transition hover:border-zinc-300 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700" onClick={() => setUnassignedJob(job)}>
                                        <p className="text-sm font-medium text-zinc-900">{job.title}</p>
                                        <p className="mt-1 font-mono text-xs text-zinc-500">{job.reference}</p>
                                        <p className="mt-1 text-xs text-zinc-500">
                                            {job.requested_at ? job.requested_at.slice(11, 16) : t.common.none}
                                            {job.area ? ` · ${job.area}` : ''}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            <PriorityBadge value={job.priority} />
                                            {job.category ? <Chip tone="brand">{job.category}</Chip> : null}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </Card>
                    <div className="min-w-0">
                        {dayEmpty ? (
                            <div className="space-y-3">
                                <EmptyState title={t.dispatch.emptyDay} description={t.dispatch.emptyDayHint} />
                                {board.nearest_day ? (
                                    <div className="flex justify-center">
                                        <Button variant="secondary" onClick={() => visit(board.nearest_day ?? board.date)}>
                                            {t.dispatch.nearestDay}: {board.nearest_day}
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
                                        <Card key={technician.id} className="p-4 shadow-sm">
                                            <TechnicianHeading technician={technician} roleLabel={technician.role ? optionLabel(t.labels, technician.role) : null} />
                                            <div className="mt-3 space-y-2">
                                                {blocks(technician).map(({ job }) => (
                                                    <button key={`${job.id}-agenda`} type="button" className={`w-full rounded-lg px-3 py-2 text-start ${blockClass(job)}`} onClick={() => openJob(job)}>
                                                        <p className="text-sm font-medium">{job.title}</p>
                                                        <p className="font-mono text-[10px] text-zinc-500">{job.reference}</p>
                                                        <p className="text-xs tabular-nums text-zinc-500">{job.start?.slice(11, 16)} – {job.end?.slice(11, 16)}</p>
                                                        <p className="truncate text-xs text-zinc-500">{job.customer_name}</p>
                                                        <StatusBadge value={job.status} />
                                                    </button>
                                                ))}
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                                <Card className="hidden overflow-x-auto shadow-sm lg:block">
                                    <div className="grid" style={{ gridTemplateColumns: `4.5rem repeat(${technicians.length}, minmax(10.5rem, 1fr))`, minWidth: `${4.5 + technicians.length * 10.5}rem` }}>
                                        <div className="sticky start-0 z-20 bg-white" />
                                        {technicians.map((technician) => (
                                            <div key={technician.id} className="border-b border-s border-zinc-200 p-3">
                                                <TechnicianHeading technician={technician} roleLabel={technician.role ? optionLabel(t.labels, technician.role) : null} />
                                            </div>
                                        ))}
                                        <div className="sticky start-0 z-20 bg-white">
                                            {hours.map((hour) => (
                                                <div key={hour} className="h-[3.25rem] border-t border-zinc-100 px-2 pt-1 text-[11px] font-medium tabular-nums text-zinc-600">
                                                    {clockLabel(hour)}
                                                </div>
                                            ))}
                                        </div>
                                        {technicians.map((technician) => (
                                            <div key={`${technician.id}-lane`} data-lane data-technician-id={technician.id} className="relative border-s border-zinc-200" style={{ height: `${hours.length * 3.25}rem` }}>
                                                {hours.map((hour) => (
                                                    <div key={hour} className={`absolute inset-x-0 border-t border-zinc-100 ${hour < workStart || hour >= workEnd ? 'bg-zinc-50/80' : ''}`} style={{ top: `${((hour - gridStart) / span) * 100}%`, height: `${(60 / span) * 100}%` }} />
                                                ))}
                                                {board.is_today && nowMinutes !== null && nowMinutes >= gridStart && nowMinutes <= gridEnd ? (
                                                    <div className="absolute inset-x-0 z-10 border-t-2 border-red-600" style={{ top: `${((nowMinutes - gridStart) / span) * 100}%` }} />
                                                ) : null}
                                                {drag?.technicianId === technician.id ? (
                                                    <div
                                                        className="pointer-events-none absolute inset-x-1 z-20 rounded-md border border-dashed border-teal-700 bg-teal-50/80 px-2 py-1 text-xs shadow-sm"
                                                        style={{ top: `${((drag.start - gridStart) / span) * 100}%`, height: `${(Math.max(drag.duration, 20) / span) * 100}%` }}
                                                    >
                                                        <span className="block truncate font-medium">{drag.job.title}</span>
                                                        <span className="block tabular-nums text-zinc-500">{clockLabel(drag.start)}–{clockLabel(drag.start + drag.duration)}</span>
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
                                                            className={`absolute inset-x-1 z-10 flex flex-col items-start overflow-hidden rounded-md px-2 py-1 text-start text-xs leading-tight transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 ${blockClass(job)} ${drag?.job.id === job.id ? 'opacity-40' : ''} ${draggable ? 'cursor-grab' : ''}`}
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
                                                            <span className="block truncate font-medium">{job.title}</span>
                                                            <span className="block truncate tabular-nums text-zinc-500">{clockLabel(start)}–{clockLabel(end)}</span>
                                                            <span className="block truncate text-zinc-500">{job.customer_name}</span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        ))}
                                    </div>
                                    <p className="px-4 py-2 text-xs text-zinc-500">{t.dispatch.gridNote}</p>
                                </Card>
                            </>
                        )}
                    </div>
                </div>
            </RecordList>
            <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <Card className="overflow-x-auto shadow-sm">
                    <h2 className="px-4 pt-4 text-sm font-semibold text-zinc-900">{t.dispatch.technicians}</h2>
                    <table className="min-w-full text-start text-sm">
                        <thead className="text-zinc-500">
                            <tr>
                                <th className="px-4 py-2 text-start text-xs font-medium">{t.ops.technician}</th>
                                <th className="px-4 py-2 text-start text-xs font-medium">{t.ops.skills}</th>
                                <th className="px-4 py-2 text-start text-xs font-medium">{t.ops.availability}</th>
                                <th className="px-4 py-2 text-start text-xs font-medium">{t.dispatch.utilization}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {technicians.map((technician) => (
                                <tr key={`${technician.id}-row`}>
                                    <td className="px-4 py-3 font-medium">{technician.name ?? t.common.none}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {technician.skills.length === 0 ? t.common.none : technician.skills.map((item) => <Chip key={item} tone="brand">{optionLabel(t.labels, item)}</Chip>)}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge value={technician.availability} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="tabular-nums">{technician.utilization}%</span>
                                            <span className="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-100">
                                                <span className="block h-full bg-teal-700" style={{ width: `${Math.min(technician.utilization, 100)}%` }} />
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="px-4 pb-4 text-xs text-zinc-500">{t.dispatch.workingDay}</p>
                </Card>
                <Card className="space-y-4 p-4 shadow-sm">
                    <h2 className="text-sm font-semibold text-zinc-900">{t.dispatch.overview}</h2>
                    <Ring label={t.dispatch.completed} value={board.counts.completed} total={board.counts.total} />
                    <Ring label={t.dispatch.unassigned} value={board.counts.unassigned} total={board.counts.total} />
                    <Ring label={t.dispatch.overdue} value={board.counts.overdue} total={board.counts.total} />
                    <p className="text-xs tabular-nums text-zinc-500">
                        {t.dispatch.total}: {board.counts.total}
                    </p>
                </Card>
            </div>
            {unassignedJob ? (
                <Drawer title={unassignedJob.type === 'work_order' ? t.dispatch.assign : t.dispatch.schedule} onClose={() => setUnassignedJob(null)} closeLabel={t.dispatch.close}>
                    <p className="text-sm font-medium text-zinc-900">{unassignedJob.title}</p>
                    <p className="mt-1 font-mono text-xs text-zinc-500">{unassignedJob.reference}</p>
                    <p className="mt-3 text-xs text-zinc-500">{t.dispatch.suggestion}</p>
                    <div className="mt-4 space-y-3">
                        {ranked.map((item, index) => (
                            <div key={item.technician.id} className="rounded-xl border border-zinc-200 p-3 shadow-sm">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-medium">{item.technician.name}</p>
                                    {index === 0 && item.slots.length > 0 ? <Chip tone="brand">{t.dispatch.bestMatch}</Chip> : null}
                                </div>
                                <p className="mt-1 text-xs tabular-nums text-zinc-500">{item.technician.utilization}%</p>
                                {unassignedJob.type === 'work_order' ? (
                                    <Button className="mt-2" variant="secondary" disabled={!canAssign} onClick={() => assign(unassignedJob, item.technician.id)}>
                                        {t.dispatch.assign}
                                    </Button>
                                ) : item.slots.length === 0 ? (
                                    <p className="mt-1 text-xs text-zinc-500">{t.dispatch.noSlots}</p>
                                ) : (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {item.slots.map((slot) => (
                                            <Button key={`${slot.start}-${slot.end}`} variant="secondary" disabled={!canSchedule} onClick={() => schedule(unassignedJob, item.technician.id, slot)}>
                                                {clockLabel(slot.start)}–{clockLabel(slot.end)}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                        {ranked.length === 0 ? <p className="text-sm text-zinc-500">{t.dispatch.noMatch}</p> : null}
                    </div>
                </Drawer>
            ) : null}
            {selected ? (
                <Drawer title={t.dispatch.details} onClose={() => setSelected(null)} closeLabel={t.dispatch.close}>
                    <p className="text-sm font-medium text-zinc-900">{selected.title}</p>
                    <p className="mt-1 font-mono text-xs text-zinc-500">{selected.reference}</p>
                    <dl className="mt-3 space-y-2 text-sm">
                        <div>
                            <dt className="text-zinc-500">{t.dispatch.customer}</dt>
                            <dd>{selected.customer_name ?? t.common.none}</dd>
                        </div>
                        <div>
                            <dt className="text-zinc-500">{t.dispatch.time}</dt>
                            <dd className="tabular-nums">{selected.start?.slice(11, 16)} – {selected.end?.slice(11, 16)}</dd>
                        </div>
                        <div className="flex flex-wrap gap-1">
                            <StatusBadge value={selected.status} />
                            {selected.priority ? <PriorityBadge value={selected.priority} /> : null}
                            {selected.category ? <Chip tone="brand">{selected.category}</Chip> : null}
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
                            <label className="block text-sm">
                                <span className="text-zinc-500">{t.ops.technician}</span>
                                <select className={`${controlClassName} mt-1`} value={draftTechnician} onChange={(event) => setDraftTechnician(event.target.value)}>
                                    {board.technicians.map((technician) => (
                                        <option key={technician.id} value={technician.id}>
                                            {technician.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <label className="block text-sm">
                                    <span className="text-zinc-500">{t.ops.start}</span>
                                    <input className={`${controlClassName} mt-1`} type="time" step={900} value={draftStart} onChange={(event) => setDraftStart(event.target.value)} />
                                </label>
                                <label className="block text-sm">
                                    <span className="text-zinc-500">{t.ops.end}</span>
                                    <input className={`${controlClassName} mt-1`} type="time" step={900} value={draftEnd} onChange={(event) => setDraftEnd(event.target.value)} />
                                </label>
                            </div>
                            <Button type="submit" variant="primary">{t.dispatch.reschedule}</Button>
                        </form>
                    ) : null}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {selected.service_request_id && (permissions.includes('*') || permissions.includes('requests.view')) ? (
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

function TechnicianHeading({ technician, roleLabel }: { technician: TechnicianColumn; roleLabel: string | null }) {
    return (
        <div className="flex items-center gap-2">
            <span className="relative">
                <Initials name={technician.name ?? ''} />
                <span className={`absolute -bottom-0.5 -end-0.5 size-2.5 rounded-full border border-white ${technician.availability === 'available' ? 'bg-green-600' : technician.availability === 'busy' ? 'bg-amber-500' : 'bg-zinc-400'}`} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-zinc-900">{technician.name}</p>
                <p className="truncate text-xs text-zinc-500">{roleLabel ?? technician.branch}</p>
                <span className="mt-1 block h-1.5 overflow-hidden rounded-full bg-zinc-100">
                    <span className="block h-full bg-teal-700" style={{ width: `${Math.min(technician.utilization, 100)}%` }} />
                </span>
            </div>
        </div>
    );
}

function Drawer({ title, onClose, closeLabel, children }: { title: string; onClose: () => void; closeLabel: string; children: ReactNode }) {
    return (
        <div className="fixed inset-0 z-40 flex justify-end bg-zinc-950/40">
            <button type="button" className="absolute inset-0" aria-label={closeLabel} onClick={onClose} />
            <aside className="relative z-10 h-full w-full max-w-md overflow-y-auto border-s border-zinc-200 bg-white p-5 shadow-xl">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold text-zinc-900">{title}</h2>
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
