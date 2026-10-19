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
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <h1 className="text-[26px] font-semibold leading-8 tracking-tight text-zinc-900">{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-zinc-500">{subtitle}</p> : null}
            </div>
            {action}
        </div>
    );
}
