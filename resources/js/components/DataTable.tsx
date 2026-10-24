import { Link } from '@inertiajs/react';
import { useId, type ReactNode } from 'react';
import { RowActionMenu, type RowAction } from '@/components/RowActionMenu';
import { useVisitState } from '@/components/VisitState';
import { useI18n } from '@/i18n';
import { DESKTOP_QUERY, useMediaQuery } from '@/lib/useMediaQuery';

export type { RowAction } from '@/components/RowActionMenu';

export type Column<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** The record's name/title: bold in the table, the card heading on mobile. One per table. */
    primary?: boolean;
    /** Right-aligned (end-aligned in RTL), tabular figures. Use for money, counts, durations. */
    numeric?: boolean;
    /**
     * Plain text for a single-line, truncated cell. The full text goes in the title
     * tooltip and the visible text is direction-isolated (dir="auto").
     */
    truncate?: (row: T) => string | null | undefined;
    /** Isolate the cell's direction (dir="auto") without truncating. */
    isolate?: boolean;
    /** Leave the column out of the mobile card. */
    hideOnMobile?: boolean;
};

const SKELETON_ROWS = 5;

function CellContent<T>({ column, row }: { column: Column<T>; row: T }) {
    if (column.truncate) {
        const text = column.truncate(row) ?? '';

        return (
            <span dir="auto" title={text} className="inline-block max-w-64 truncate align-middle">
                {column.cell(row)}
            </span>
        );
    }

    if (column.isolate) {
        return <span dir="auto">{column.cell(row)}</span>;
    }

    return <>{column.cell(row)}</>;
}

function InlineAction({ action }: { action: RowAction }) {
    const className = `inline-flex min-h-9 items-center rounded-control px-2.5 text-body font-medium hover:bg-surface-muted ${action.tone === 'danger' ? 'text-danger' : 'text-brand hover:text-brand-strong'}`;

    return action.href ? (
        <Link href={action.href} className={className}>
            {action.label}
        </Link>
    ) : (
        <button type="button" className={className} onClick={action.onClick}>
            {action.label}
        </button>
    );
}

function RowActions({ actions, describedBy }: { actions: RowAction[]; describedBy: string }) {
    const inline = actions.filter((action) => action.inline);
    const menu = actions.filter((action) => !action.inline);

    return (
        <div className="flex items-center justify-end gap-1">
            {inline.map((action) => (
                <InlineAction key={action.key} action={action} />
            ))}
            {menu.length > 0 ? <RowActionMenu actions={menu} describedBy={describedBy} /> : null}
        </div>
    );
}

/**
 * Index-page table. Renders a real <table> at md and up and a stacked card list below,
 * never both. Rows are replaced by skeletons while a filter or page visit is loading.
 * `actions` returns the row's actions: `inline` ones stay visible, the rest go in a menu.
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    actions,
    caption,
}: {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    actions?: (row: T) => RowAction[];
    caption?: string;
}) {
    const { t } = useI18n();
    const { loading } = useVisitState();
    const tableId = useId();
    const labelId = (row: T) => `${tableId}-${rowKey(row)}`;
    const desktop = useMediaQuery(DESKTOP_QUERY);
    const hasActions = actions !== undefined;
    const primary = columns.find((column) => column.primary) ?? columns[0];
    const secondary = columns.filter((column) => column !== primary && !column.hideOnMobile);

    if (!desktop) {
        return (
            <div aria-busy={loading || undefined}>
                {loading ? <p className="sr-only" role="status">{t.list.loading}</p> : null}
                <ul className="space-y-3">
                    {loading
                        ? Array.from({ length: 3 }, (_, index) => (
                              <li key={`skeleton-${index}`} aria-hidden="true" className="space-y-3 rounded-card border border-line bg-surface p-4 shadow-card">
                                  <span className="block h-4 w-1/2 animate-pulse rounded-badge bg-line" />
                                  <span className="block h-3 w-3/4 animate-pulse rounded-badge bg-line" />
                                  <span className="block h-3 w-2/3 animate-pulse rounded-badge bg-line" />
                              </li>
                          ))
                        : rows.map((row) => {
                              const rowActions = actions?.(row) ?? [];

                              return (
                                  <li key={rowKey(row)} className="rounded-card border border-line bg-surface p-4 shadow-card">
                                      <div className="flex items-start justify-between gap-3">
                                          <div id={labelId(row)} className="min-w-0 text-body font-medium break-words text-ink">
                                              {primary.truncate || primary.isolate ? <span dir="auto">{primary.cell(row)}</span> : primary.cell(row)}
                                          </div>
                                          {rowActions.length > 0 ? <RowActions actions={rowActions} describedBy={labelId(row)} /> : null}
                                      </div>
                                      {secondary.length > 0 ? (
                                          <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-3">
                                              {secondary.map((column) => (
                                                  <div key={column.key} className="min-w-0">
                                                      <dt className="text-caption text-ink-muted">{column.header}</dt>
                                                      <dd className={`mt-0.5 text-body break-words text-ink-secondary ${column.numeric ? 'tabular-nums' : ''}`}>
                                                          {column.truncate || column.isolate ? <span dir="auto">{column.cell(row)}</span> : column.cell(row)}
                                                      </dd>
                                                  </div>
                                              ))}
                                          </dl>
                                      ) : null}
                                  </li>
                              );
                          })}
                </ul>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-card border border-line bg-surface shadow-card" aria-busy={loading || undefined}>
            {loading ? <p className="sr-only" role="status">{t.list.loading}</p> : null}
            <table className="min-w-full text-body">
                {caption ? <caption className="sr-only">{caption}</caption> : null}
                <thead className="bg-surface-muted">
                    <tr className="border-b border-line">
                        {columns.map((column) => (
                            <th key={column.key} scope="col" className={`px-4 py-2.5 text-caption font-medium whitespace-nowrap text-ink-muted ${column.numeric ? 'text-end' : 'text-start'}`}>
                                {column.header}
                            </th>
                        ))}
                        {hasActions ? (
                            <th scope="col" className="px-4 py-2.5">
                                <span className="sr-only">{t.list.actions}</span>
                            </th>
                        ) : null}
                    </tr>
                </thead>
                <tbody className="text-ink-secondary">
                    {loading
                        ? Array.from({ length: SKELETON_ROWS }, (_, index) => (
                              <tr key={`skeleton-${index}`} aria-hidden="true">
                                  {columns.map((column) => (
                                      <td key={column.key} className="px-4 py-3.5">
                                          <span className={`block h-3 animate-pulse rounded-badge bg-line ${column.primary ? 'w-40' : column.numeric ? 'ms-auto w-16' : 'w-24'}`} />
                                      </td>
                                  ))}
                                  {hasActions ? <td className="px-4 py-3.5" /> : null}
                              </tr>
                          ))
                        : rows.map((row) => {
                              const rowActions = actions?.(row) ?? [];

                              return (
                                  <tr key={rowKey(row)}>
                                      {columns.map((column) => (
                                          <td
                                              key={column.key}
                                              id={column === primary ? labelId(row) : undefined}
                                              className={`px-4 py-3 ${column.numeric ? 'text-end whitespace-nowrap tabular-nums' : 'text-start'} ${column.primary ? 'font-medium text-ink' : ''}`}
                                          >
                                              <CellContent column={column} row={row} />
                                          </td>
                                      ))}
                                      {hasActions ? (
                                          <td className="px-2 py-1.5 text-end whitespace-nowrap">
                                              {rowActions.length > 0 ? <RowActions actions={rowActions} describedBy={labelId(row)} /> : null}
                                          </td>
                                      ) : null}
                                  </tr>
                              );
                          })}
                </tbody>
            </table>
        </div>
    );
}
