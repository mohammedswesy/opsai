import type { ReactNode } from 'react';

const tones = {
    neutral: 'bg-zinc-50 text-zinc-700 ring-zinc-200',
    brand: 'bg-teal-50 text-teal-800 ring-teal-200',
};

export function Chip({ children, tone = 'neutral' }: { children: ReactNode; tone?: keyof typeof tones }) {
    return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ${tones[tone]}`}>{children}</span>;
}
