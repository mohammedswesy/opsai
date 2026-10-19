import { router, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Card } from '@/components/Card';

export function FilterBar({ children }: { children: ReactNode }) {
    const page = usePage();

    function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const query: Record<string, string> = {};

        new FormData(event.currentTarget).forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        router.get(page.url.split('?')[0] ?? '/', query, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <Card className="mb-4">
            <form className="grid items-end gap-3 p-4 md:grid-cols-4" onSubmit={onSubmit}>
                {children}
            </form>
        </Card>
    );
}
