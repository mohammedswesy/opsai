import { router, usePage } from '@inertiajs/react';
import { SlidersHorizontal, X } from 'lucide-react';
import { useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react';
import { useI18n } from '@/i18n';
import { fill } from '@/lib/text';

export type ActiveFilter = { name: string; label: string; value: string };

/**
 * Builds the chip list from the current filters. `defs` maps a query key to its label
 * and an optional formatter for the value (e.g. a status key to its translation).
 */
export function activeFilters(
    filters: Record<string, string | undefined | null>,
    defs: Record<string, { label: string; value?: (raw: string) => string }>,
): ActiveFilter[] {
    return Object.entries(defs).flatMap(([name, def]) => {
        const raw = filters[name];

        return raw ? [{ name, label: def.label, value: def.value ? def.value(raw) : raw }] : [];
    });
}

const buttonClassName = 'inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-control px-3 text-body font-medium';

/**
 * GET filter form for index pages: a search field, auto-fitting filter controls
 * (collapsed behind a "Filters" toggle on small screens) and removable chips for the
 * filters in effect. Selects, dates and checkboxes apply as soon as they change, so
 * the Search button only appears when there is a search field.
 */
export function FilterBar({ search, children, active = [] }: { search?: ReactNode; children?: ReactNode; active?: ActiveFilter[] }) {
    const { t } = useI18n();
    const page = usePage();
    const path = page.url.split('?')[0] ?? '/';
    const controlFilters = active.filter((filter) => filter.name !== 'search').length;
    const [open, setOpen] = useState(controlFilters > 0);

    function visit(query: Record<string, string>) {
        router.get(path, query, { preserveState: true, preserveScroll: true, replace: true });
    }

    function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const query: Record<string, string> = {};

        new FormData(event.currentTarget).forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        visit(query);
    }

    function onChange(event: ChangeEvent<HTMLFormElement>) {
        const target = event.target as unknown as HTMLInputElement | HTMLSelectElement;

        if (target.tagName === 'SELECT' || ['date', 'checkbox'].includes((target as HTMLInputElement).type)) {
            event.currentTarget.requestSubmit();
        }
    }

    function remove(name: string) {
        const query: Record<string, string> = {};

        new URLSearchParams(window.location.search).forEach((value, key) => {
            if (key !== name && key !== 'page' && value !== '') {
                query[key] = value;
            }
        });

        visit(query);
    }

    return (
        <div className="mb-4 rounded-card border border-line bg-surface p-3 shadow-card sm:p-4">
            <form onSubmit={onSubmit} onChange={onChange} className="space-y-3">
                <div className={`gap-2 ${search ? 'flex' : 'flex md:hidden'}`}>
                    {search ? <div className="min-w-0 flex-1">{search}</div> : null}
                    {children ? (
                        <button
                            type="button"
                            className={`${buttonClassName} border border-line bg-surface text-ink-secondary hover:bg-surface-muted md:hidden`}
                            aria-expanded={open}
                            onClick={() => setOpen((current) => !current)}
                        >
                            <SlidersHorizontal className="size-4" strokeWidth={1.75} aria-hidden="true" />
                            <span className={search ? 'sr-only sm:not-sr-only' : ''}>{t.list.filters}</span>
                            {controlFilters > 0 ? <span className="rounded-badge bg-brand px-1.5 text-micro font-semibold text-on-brand tabular-nums">{controlFilters}</span> : null}
                        </button>
                    ) : null}
                    {search ? (
                        <button type="submit" className={`${buttonClassName} bg-brand text-on-brand hover:bg-brand-strong`}>
                            {t.common.search}
                        </button>
                    ) : null}
                </div>
                {children ? <div className={`${open ? 'grid' : 'hidden'} grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] items-center gap-3 md:grid`}>{children}</div> : null}
            </form>
            {active.length > 0 ? (
                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                    <span className="sr-only">{t.list.activeFilters}</span>
                    <ul className="flex flex-wrap items-center gap-2">
                        {active.map((filter) => (
                            <li key={filter.name} className="inline-flex items-center gap-1 rounded-badge border border-brand-border bg-brand-soft py-0.5 ps-2.5 pe-1 text-caption text-brand-strong">
                                {filter.value ? (
                                    <span>
                                        {filter.label}: <bdi className="font-medium">{filter.value}</bdi>
                                    </span>
                                ) : (
                                    <span className="font-medium">{filter.label}</span>
                                )}
                                <button
                                    type="button"
                                    className="inline-flex size-5 items-center justify-center rounded-badge hover:bg-brand-border"
                                    aria-label={fill(t.list.removeFilter, { label: filter.label })}
                                    onClick={() => remove(filter.name)}
                                >
                                    <X className="size-3" strokeWidth={2} aria-hidden="true" />
                                </button>
                            </li>
                        ))}
                    </ul>
                    <button type="button" className="text-caption font-medium text-brand hover:text-brand-strong hover:underline" onClick={() => visit({})}>
                        {t.list.clearFilters}
                    </button>
                </div>
            ) : null}
        </div>
    );
}
