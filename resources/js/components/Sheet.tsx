import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { IconButton } from '@/components/Button';
import { Dialog, DialogTitle } from '@/components/Dialog';

/**
 * Panel that slides in from the start edge on small screens, built on `Dialog`: focus
 * moves into it, Tab stays inside, Escape or the backdrop closes it, and focus returns
 * to whatever opened it.
 */
export function Sheet({ open, onClose, title, closeLabel, children }: { open: boolean; onClose: () => void; title: string; closeLabel: string; children: ReactNode }) {
    return (
        <Dialog open={open} onClose={onClose} placement="start" className="flex h-full w-80 max-w-[85vw] flex-col border-e border-line bg-surface shadow-overlay">
            <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <DialogTitle className="text-heading font-semibold text-ink">{title}</DialogTitle>
                <IconButton label={closeLabel} size="sm" className="text-ink-muted hover:text-ink" onClick={onClose}>
                    <X className="size-4" strokeWidth={1.75} aria-hidden="true" />
                </IconButton>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto p-4">{children}</div>
        </Dialog>
    );
}
