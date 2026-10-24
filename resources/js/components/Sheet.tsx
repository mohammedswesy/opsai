import { X } from 'lucide-react';
import { useEffect, useId, useRef, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Panel that slides in from the start edge on small screens. It is a modal dialog:
 * focus moves into it, Tab stays inside, Escape or the backdrop closes it, and focus
 * returns to whatever opened it.
 */
export function Sheet({ open, onClose, title, closeLabel, children }: { open: boolean; onClose: () => void; title: string; closeLabel: string; children: ReactNode }) {
    const titleId = useId();
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const previous = document.activeElement as HTMLElement | null;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panelRef.current?.querySelector<HTMLElement>(FOCUSABLE)?.focus();

        return () => {
            document.body.style.overflow = overflow;
            previous?.focus();
        };
    }, [open]);

    if (!open) {
        return null;
    }

    function onKeyDown(event: ReactKeyboardEvent<HTMLDivElement>) {
        if (event.key === 'Escape') {
            event.stopPropagation();
            onClose();
            return;
        }

        if (event.key !== 'Tab' || !panelRef.current) {
            return;
        }

        const items = [...panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE)];
        const first = items[0];
        const last = items.at(-1);

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex" onKeyDown={onKeyDown}>
            <button type="button" tabIndex={-1} className="absolute inset-0 bg-ink/40" aria-label={closeLabel} onClick={onClose} />
            <div ref={panelRef} role="dialog" aria-modal="true" aria-labelledby={titleId} className="relative flex h-full w-80 max-w-[85vw] flex-col border-e border-line bg-surface shadow-overlay">
                <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <h2 id={titleId} className="text-heading font-semibold text-ink">
                        {title}
                    </h2>
                    <button type="button" className="rounded-control p-1.5 text-ink-muted hover:bg-surface-muted hover:text-ink" aria-label={closeLabel} onClick={onClose}>
                        <X className="size-4" strokeWidth={1.75} aria-hidden="true" />
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto p-4">{children}</div>
            </div>
        </div>
    );
}
