export function Initials({ name, className = '' }: { name: string; className?: string }) {
    const letters = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();

    return (
        <span className={`flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-700 ${className}`.trim()}>
            {letters || '·'}
        </span>
    );
}
