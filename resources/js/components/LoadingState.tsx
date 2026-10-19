import { Card } from '@/components/Card';
import { Skeleton } from '@/components/Skeleton';

export function LoadingState({ label }: { label: string }) {
    return (
        <Card className="space-y-3 px-4 py-4" role="status">
            <Skeleton className="h-3 w-28" />
            <Skeleton className="h-3 w-44" />
            <Skeleton className="h-3 w-36" />
            <p className="text-sm text-zinc-500">{label}</p>
        </Card>
    );
}
