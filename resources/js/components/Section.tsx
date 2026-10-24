import type { HTMLAttributes, ReactNode } from 'react';

/**
 * A titled surface for one group of content on a record page. `flush` drops the body
 * padding so tables and lists can run edge to edge. The <h2> gives the structure; the
 * section is deliberately not a named region, so a page of cards is not a page of landmarks.
 */
export function Section({
    title,
    description,
    action,
    flush = false,
    className = '',
    children,
    ...props
}: Omit<HTMLAttributes<HTMLElement>, 'title'> & {
    title?: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    flush?: boolean;
    children: ReactNode;
}) {
    const hasHeader = Boolean(title || action);

    return (
        <section className={`min-w-0 rounded-card border border-line bg-surface shadow-card print:border-0 print:shadow-none ${className}`.trim()} {...props}>
            {hasHeader ? (
                <header className="flex items-start justify-between gap-3 px-5 pt-4">
                    <div className="min-w-0">
                        {title ? (
                            <h2 className="text-heading font-semibold text-ink">
                                {title}
                            </h2>
                        ) : null}
                        {description ? <p className="mt-0.5 text-caption text-ink-muted">{description}</p> : null}
                    </div>
                    {action ? <div className="shrink-0 print:hidden">{action}</div> : null}
                </header>
            ) : null}
            <div className={flush ? (hasHeader ? 'pt-3' : '') : hasHeader ? 'px-5 pt-3 pb-5' : 'p-5'}>{children}</div>
        </section>
    );
}
