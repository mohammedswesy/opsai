import { Link } from '@inertiajs/react';

export function Pagination({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="mt-3 flex flex-wrap items-center gap-1.5">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border px-2.5 text-sm transition-colors ${
                            link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span key={`${link.label}-${index}`} className="inline-flex min-h-9 min-w-9 items-center justify-center px-2.5 text-sm text-zinc-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </nav>
    );
}
