export function EmptyState({ title }: { title: string }) {
    return (
        <div className="rounded-lg border border-dashed border-zinc-300 bg-white px-6 py-12 text-center text-sm text-zinc-500">
            {title}
        </div>
    );
}
