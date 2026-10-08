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
    'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20';
