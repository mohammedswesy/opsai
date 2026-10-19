export function Skeleton({ className = 'h-4 w-full' }: { className?: string }) {
    return <span aria-hidden="true" className={`skeleton-shimmer block rounded-md bg-zinc-200 ${className}`.trim()} />;
}
