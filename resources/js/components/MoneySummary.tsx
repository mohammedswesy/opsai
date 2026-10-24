import type { ReactNode } from 'react';

export type MoneyRow = {
    key: string;
    label: ReactNode;
    value: ReactNode;
    emphasis?: 'total' | 'strong';
    tone?: 'success' | 'danger';
};

const tones = { success: 'text-success', danger: 'text-danger' };

/** Label/amount rows with amounts aligned to the end edge, for totals and balances. */
export function MoneySummary({ rows, className = '' }: { rows: MoneyRow[]; className?: string }) {
    return (
        <dl className={`space-y-2 text-body ${className}`.trim()}>
            {rows.map((row) => (
                <div key={row.key} className={`flex items-baseline justify-between gap-4 ${row.emphasis === 'total' ? 'border-t border-line pt-3' : ''}`}>
                    <dt className={row.emphasis ? 'font-medium text-ink' : 'text-ink-secondary'}>{row.label}</dt>
                    <dd
                        className={`text-end tabular-nums ${row.emphasis === 'total' ? 'text-heading font-semibold' : row.emphasis === 'strong' ? 'font-semibold' : ''} ${row.tone ? tones[row.tone] : 'text-ink'}`}
                    >
                        {row.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
