import { AlertTriangle, Inbox, SearchX } from 'lucide-react';
import type { ReactNode } from 'react';

const icons = { empty: Inbox, filtered: SearchX, error: AlertTriangle };

/** Empty, no-results and error panel for index pages. */
export function ListState({ kind, title, description, action }: { kind: keyof typeof icons; title: string; description?: string; action?: ReactNode }) {
    const Icon = icons[kind];

    return (
        <div role={kind === 'error' ? 'alert' : undefined} className="rounded-card border border-dashed border-line-strong bg-surface px-6 py-12 text-center">
            <span className={`mx-auto flex size-10 items-center justify-center rounded-badge ${kind === 'error' ? 'bg-danger-soft text-danger' : 'bg-surface-muted text-ink-subtle'}`}>
                <Icon className="size-5" strokeWidth={1.75} aria-hidden="true" />
            </span>
            <p className="mt-3 text-body font-medium text-ink">{title}</p>
            {description ? <p className="mx-auto mt-1 max-w-sm text-body text-ink-muted">{description}</p> : null}
            {action ? <div className="mt-4 flex justify-center">{action}</div> : null}
        </div>
    );
}
