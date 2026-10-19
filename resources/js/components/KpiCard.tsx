import type { LucideIcon } from 'lucide-react';
import { Card } from '@/components/Card';

const tones = {
    default: 'bg-blue-50 text-blue-700 ring-blue-100',
    success: 'bg-green-50 text-green-700 ring-green-100',
    warning: 'bg-amber-50 text-amber-700 ring-amber-100',
    danger: 'bg-red-50 text-red-700 ring-red-100',
    info: 'bg-sky-50 text-sky-700 ring-sky-100',
};

export function KpiCard({
    label,
    value,
    icon: Icon,
    tone = 'default',
    size = 'primary',
}: {
    label: string;
    value: number | string;
    icon?: LucideIcon;
    tone?: keyof typeof tones;
    size?: 'primary' | 'quiet';
}) {
    const quiet = size === 'quiet';

    return (
        <Card className={`transition-[box-shadow,transform] duration-200 ease-out hover:-translate-y-px hover:shadow-[0_12px_28px_-8px_rgba(16,24,40,0.12)] ${quiet ? 'px-4 py-3' : 'px-5 py-4'}`}>
            <article>
                <div className="flex items-start justify-between gap-3">
                    <p className={`font-medium text-zinc-500 ${quiet ? 'text-xs' : 'text-sm'}`}>{label}</p>
                    {Icon ? (
                        <span className={`flex shrink-0 items-center justify-center rounded-xl ring-1 ${quiet ? 'size-8' : 'size-10'} ${tones[tone]}`}>
                            <Icon className={quiet ? 'size-4' : 'size-[18px]'} strokeWidth={1.75} />
                        </span>
                    ) : null}
                </div>
                <p className={`font-semibold tracking-tight text-zinc-900 tabular-nums ${quiet ? 'mt-2 text-xl leading-6' : 'mt-4 text-[30px] leading-9'}`}>{value}</p>
            </article>
        </Card>
    );
}
