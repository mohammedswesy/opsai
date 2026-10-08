export function StatusBadge({ value }: { value: string }) {
    const tone = value === 'active' ? 'bg-teal-50 text-teal-800' : 'bg-zinc-100 text-zinc-700';

    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${tone}`}>{value}</span>;
}
