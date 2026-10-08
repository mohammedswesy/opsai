export function ConfirmDialog({
    open,
    title,
    confirmLabel,
    cancelLabel,
    onConfirm,
    onCancel,
}: {
    open: boolean;
    title: string;
    confirmLabel: string;
    cancelLabel: string;
    onConfirm: () => void;
    onCancel: () => void;
}) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-4">
            <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                <p className="text-sm text-zinc-800">{title}</p>
                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" className="rounded-md px-3 py-2 text-sm text-zinc-700" onClick={onCancel}>
                        {cancelLabel}
                    </button>
                    <button type="button" className="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white" onClick={onConfirm}>
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
