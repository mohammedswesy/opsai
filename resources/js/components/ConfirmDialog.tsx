import { Button } from '@/components/Button';

export function ConfirmDialog({
    open,
    title,
    confirmLabel,
    cancelLabel,
    onConfirm,
    onCancel,
    tone = 'default',
}: {
    open: boolean;
    title: string;
    confirmLabel: string;
    cancelLabel: string;
    onConfirm: () => void;
    onCancel: () => void;
    /** `danger` gives the confirm button the destructive style (archive, cancel, refund, remove). */
    tone?: 'default' | 'danger';
}) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4">
            <div className="w-full max-w-md rounded-card border border-line bg-surface p-6 shadow-lg" role="dialog" aria-modal="true">
                <p className="text-body/6 text-ink">{title}</p>
                <div className="mt-6 flex justify-end gap-2">
                    <Button onClick={onCancel}>{cancelLabel}</Button>
                    <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
