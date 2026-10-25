import type { ReactNode } from 'react';
import { Badge } from '@/components/Badge';

/** Neutral or brand tag, such as a skill or a payment method. */
export function Chip({ children, tone = 'neutral' }: { children: ReactNode; tone?: 'neutral' | 'brand' }) {
    return <Badge tone={tone}>{children}</Badge>;
}
