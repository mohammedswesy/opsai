import { Head } from '@inertiajs/react';
import { createContext, useContext, useLayoutEffect, type ReactNode } from 'react';

export type PageParent = { label: string; href: string };
export type PageMeta = { title: string; parent?: PageParent };

const PageMetaContext = createContext<(meta: PageMeta) => void>(() => {});

export function PageMetaProvider({ onChange, children }: { onChange: (meta: PageMeta) => void; children: ReactNode }) {
    return <PageMetaContext.Provider value={onChange}>{children}</PageMetaContext.Provider>;
}

/**
 * Sets the browser tab title and tells the app shell what to show in the top bar.
 * `parent` adds a breadcrumb step (e.g. Work orders › Seeded completed visit) and is
 * folded into the tab title as "Title · Parent".
 */
export function PageTitle({ title, parent }: PageMeta) {
    const setMeta = useContext(PageMetaContext);

    useLayoutEffect(() => {
        setMeta({ title, parent });
    }, [parent?.href, parent?.label, setMeta, title]);

    return <Head title={parent ? `${title} · ${parent.label}` : title} />;
}
