import type { ReactNode } from 'react';

export function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="block space-y-1.5">
            <span className="text-sm font-medium text-zinc-700">{label}</span>
            {children}
            {error ? <span className="text-sm text-red-600">{error}</span> : null}
        </label>
    );
}

export const controlClassName =
    'min-h-10 w-full rounded-[10px] border border-zinc-200/80 bg-white px-3 text-sm text-zinc-900 outline-none';
