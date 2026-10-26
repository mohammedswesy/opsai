import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Dialog, DialogTitle } from '@/components/Dialog';
import { useI18n } from '@/i18n';

export type CommandLink = { href: string; label: string; group: string };

function fill(template: string, query: string): string {
    return template.replaceAll('{query}', query);
}

export function CommandPalette({
    open,
    onClose,
    links,
    canSearchCustomers,
    canSearchRequests,
}: {
    open: boolean;
    onClose: () => void;
    links: CommandLink[];
    canSearchCustomers: boolean;
    canSearchRequests: boolean;
}) {
    const { t } = useI18n();
    const listId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);

    const items = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase();
        const pages = links.filter((link) => needle === '' || link.label.toLocaleLowerCase().includes(needle));
        const search: CommandLink[] = [];

        if (needle !== '') {
            if (canSearchCustomers) {
                search.push({ href: `/customers?search=${encodeURIComponent(query.trim())}`, label: fill(t.nav.searchCustomers, query.trim()), group: t.nav.customers });
            }
            if (canSearchRequests) {
                search.push({ href: `/requests?search=${encodeURIComponent(query.trim())}`, label: fill(t.nav.searchRequests, query.trim()), group: t.nav.requests });
            }
        }

        return [...search, ...pages];
    }, [canSearchCustomers, canSearchRequests, links, query, t.nav.customers, t.nav.requests, t.nav.searchCustomers, t.nav.searchRequests]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setQuery('');
        setActive(0);
    }, [open]);

    useEffect(() => {
        setActive(0);
    }, [query]);

    return (
        <Dialog open={open} onClose={onClose} placement="top" initialFocusRef={inputRef} className="w-full max-w-xl overflow-hidden rounded-overlay border border-line/70 bg-surface shadow-overlay">
            <DialogTitle className="sr-only">{t.nav.command}</DialogTitle>
            <div className="flex items-center gap-2 border-b border-line/80 px-4 focus-within:shadow-[inset_0_-2px_0_var(--color-focus)]">
                <Search className="size-[18px] shrink-0 text-ink-muted" strokeWidth={1.75} aria-hidden="true" />
                <input
                    ref={inputRef}
                    role="combobox"
                    aria-expanded={items.length > 0}
                    aria-autocomplete="list"
                    aria-label={t.nav.searchPlaceholder}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t.nav.searchPlaceholder}
                    aria-controls={listId}
                    aria-activedescendant={items[active] ? `${listId}-${active}` : undefined}
                    className="h-12 w-full border-0 bg-transparent text-body text-ink outline-none ring-0 placeholder:text-ink-muted"
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setActive((current) => Math.min(items.length - 1, current + 1));
                        }
                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setActive((current) => Math.max(0, current - 1));
                        }
                        if (event.key === 'Enter' && items[active]) {
                            event.preventDefault();
                            (document.getElementById(`${listId}-${active}`) as HTMLAnchorElement | null)?.click();
                        }
                    }}
                />
            </div>
            <ul id={listId} role="listbox" aria-label={t.nav.command} className="max-h-80 overflow-y-auto p-2">
                {items.length === 0 ? (
                    <li role="presentation" className="px-3 py-6 text-center text-body text-ink-muted">
                        {t.nav.noMatches}
                    </li>
                ) : null}
                {items.map((item, index) => (
                    <li key={`${item.href}-${item.label}`} role="presentation">
                        {/* Options are reached with the arrow keys from the input, so they stay out of the Tab order. */}
                        <Link
                            id={`${listId}-${index}`}
                            role="option"
                            tabIndex={-1}
                            aria-selected={index === active}
                            href={item.href}
                            className={`flex items-center justify-between gap-3 rounded-control px-3 py-2 text-body ${index === active ? 'bg-brand-soft text-brand-strong' : 'text-ink-body'}`}
                            onMouseEnter={() => setActive(index)}
                            onClick={onClose}
                        >
                            <span className="truncate">{item.label}</span>
                            <span className="shrink-0 text-caption text-ink-muted">{item.group}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </Dialog>
    );
}
