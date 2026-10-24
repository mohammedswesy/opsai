import type { ReactNode } from 'react';
import { PageTitle, type PageParent } from '@/components/PageTitle';

export function PageHeader({
    title,
    subtitle,
    action,
    parent,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    /** Breadcrumb parent shown in the top bar and added to the tab title. */
    parent?: PageParent;
}) {
    return (
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <PageTitle title={title} parent={parent} />
            <div className="min-w-0">
                <h1 className="text-[26px] font-semibold leading-8 tracking-tight text-zinc-900">{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-zinc-500">{subtitle}</p> : null}
            </div>
            {action}
        </div>
    );
}
