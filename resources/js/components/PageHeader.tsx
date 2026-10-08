import type { ReactNode } from 'react';

export function PageHeader({
    title,
    subtitle,
    action,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}) {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-semibold text-zinc-900">{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-zinc-500">{subtitle}</p> : null}
            </div>
            {action}
        </div>
    );
}
