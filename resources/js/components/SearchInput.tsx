import { Search } from 'lucide-react';

export function SearchInput({
    name = 'search',
    defaultValue,
    placeholder,
}: {
    name?: string;
    defaultValue?: string;
    placeholder: string;
}) {
    return (
        <span className="relative block min-w-0">
            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-subtle" strokeWidth={1.75} aria-hidden="true" />
            <input
                type="search"
                className="min-h-10 w-full rounded-control border border-line bg-surface ps-9 pe-3 text-body text-ink placeholder:text-ink-subtle"
                name={name}
                defaultValue={defaultValue ?? ''}
                placeholder={placeholder}
                aria-label={placeholder}
            />
        </span>
    );
}
