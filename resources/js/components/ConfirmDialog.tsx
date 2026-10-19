import { Button } from '@/components/Button';

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
            <div className="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg" role="dialog" aria-modal="true">
                <p className="text-sm leading-6 text-zinc-800">{title}</p>
                <div className="mt-6 flex justify-end gap-2">
                    <Button onClick={onCancel}>{cancelLabel}</Button>
                    <Button variant="primary" onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
