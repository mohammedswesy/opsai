import type { HTMLAttributes } from 'react';

/** Body row for data tables: hairline divider, hover tint and tabular figures for every cell. */
export const tableRowClassName = 'border-b border-line-subtle tabular-nums transition-colors duration-120 last:border-b-0 hover:bg-surface-muted';

export function TableRow({ className = '', ...props }: HTMLAttributes<HTMLTableRowElement>) {
    return <tr className={`${tableRowClassName} ${className}`.trim()} {...props} />;
}
