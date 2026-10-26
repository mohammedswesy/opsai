import { useRef } from 'react';
import { Button } from '@/components/Button';
import { Dialog, DialogTitle } from '@/components/Dialog';

/**
 * Confirmation as an `alertdialog`. Focus starts on Cancel so Enter never confirms a
 * destructive action by accident; Escape and the backdrop cancel.
 */
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
    const cancelRef = useRef<HTMLButtonElement>(null);

    return (
        <Dialog open={open} onClose={onCancel} role="alertdialog" initialFocusRef={cancelRef} className="w-full max-w-md rounded-card border border-line bg-surface p-6 shadow-overlay">
            <DialogTitle className="text-body/6 font-normal text-ink">{title}</DialogTitle>
            <div className="mt-6 flex justify-end gap-2">
                <Button ref={cancelRef} onClick={onCancel}>
                    {cancelLabel}
                </Button>
                <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm}>
                    {confirmLabel}
                </Button>
            </div>
        </Dialog>
    );
}
