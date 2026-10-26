import { createContext, useContext, useEffect, useId, useRef, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusableIn(container: HTMLElement): HTMLElement[] {
    return [...container.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((element) => element.tabIndex >= 0 && element.getClientRects().length > 0);
}

// Several overlays can be open at once (a confirmation over a sheet): only the top one
// handles keys, and the scroll lock is counted.
const openModals: object[] = [];
let scrollLocks = 0;
let savedOverflow = '';

function lockScroll(): () => void {
    if (scrollLocks++ === 0) {
        savedOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }

    return () => {
        if (--scrollLocks === 0) {
            document.body.style.overflow = savedOverflow;
        }
    };
}

/** Marks everything outside `root` inert (siblings of root and of each ancestor), and undoes only what it changed. */
function inertOutside(root: HTMLElement): () => void {
    const changed: HTMLElement[] = [];

    for (let node: HTMLElement = root; node.parentElement && node !== document.body; node = node.parentElement) {
        for (const sibling of node.parentElement.children) {
            if (sibling !== node && sibling instanceof HTMLElement && !sibling.inert && sibling.tagName !== 'SCRIPT') {
                sibling.inert = true;
                changed.push(sibling);
            }
        }
    }

    return () => changed.forEach((element) => (element.inert = false));
}

/**
 * Modal behaviour for an overlay: while `open`, the page outside `rootRef` is inert and
 * does not scroll, focus moves into `panelRef` (to `initialFocusRef`, else the first
 * control, else the panel), Tab wraps inside the panel, Escape calls `onClose`, and on
 * close focus goes back to whatever had it before.
 */
export function useModal({
    open,
    onClose,
    rootRef,
    panelRef,
    initialFocusRef,
}: {
    open: boolean;
    onClose: () => void;
    rootRef: RefObject<HTMLElement | null>;
    panelRef: RefObject<HTMLElement | null>;
    initialFocusRef?: RefObject<HTMLElement | null>;
}): void {
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        const root = rootRef.current;
        const panel = panelRef.current;

        if (!open || !root || !panel) {
            return;
        }

        const token = {};
        const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        openModals.push(token);
        const unlock = lockScroll();
        const restoreInert = inertOutside(root);
        (initialFocusRef?.current ?? focusableIn(panel)[0] ?? panel).focus();

        function onKeyDown(event: KeyboardEvent) {
            if (openModals.at(-1) !== token) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                onCloseRef.current();
                return;
            }

            if (event.key !== 'Tab' || !panel) {
                return;
            }

            const items = focusableIn(panel);
            const first = items[0];
            const last = items.at(-1);

            if (!first || !last) {
                event.preventDefault();
                panel.focus();
            } else if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            openModals.splice(openModals.indexOf(token), 1);
            restoreInert();
            unlock();
            if (previous?.isConnected) {
                previous.focus();
            }
        };
    }, [initialFocusRef, open, panelRef, rootRef]);
}

const TitleContext = createContext<string | undefined>(undefined);

/** The dialog's heading; it names the dialog through `aria-labelledby`. */
export function DialogTitle({ className = '', children }: { className?: string; children: ReactNode }) {
    const id = useContext(TitleContext);

    return (
        <h2 id={id} className={className}>
            {children}
        </h2>
    );
}

const placements = {
    center: 'items-center justify-center p-4',
    top: 'items-start justify-center px-4 pt-[12vh]',
    start: 'justify-start',
    end: 'justify-end',
};

/**
 * Modal overlay rendered into `document.body`: a backdrop and a panel with `role`
 * `dialog` (or `alertdialog` for confirmations), named by its `DialogTitle`. It uses
 * `useModal` for focus, Escape, inert background and scroll lock. Clicking the backdrop
 * closes it unless `closeOnBackdrop` is false.
 */
export function Dialog({
    open,
    onClose,
    role = 'dialog',
    placement = 'center',
    closeOnBackdrop = true,
    initialFocusRef,
    describedBy,
    className = '',
    children,
}: {
    open: boolean;
    onClose: () => void;
    role?: 'dialog' | 'alertdialog';
    placement?: keyof typeof placements;
    closeOnBackdrop?: boolean;
    initialFocusRef?: RefObject<HTMLElement | null>;
    describedBy?: string;
    className?: string;
    children: ReactNode;
}) {
    const titleId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);

    useModal({ open, onClose, rootRef, panelRef, initialFocusRef });

    if (!open) {
        return null;
    }

    return createPortal(
        <div ref={rootRef} className={`fixed inset-0 z-50 flex ${placements[placement]}`}>
            <div aria-hidden="true" className="absolute inset-0 bg-ink/40" onClick={closeOnBackdrop ? onClose : undefined} />
            <div ref={panelRef} role={role} aria-modal="true" aria-labelledby={titleId} aria-describedby={describedBy} tabIndex={-1} className={`relative outline-none ${className}`.trim()}>
                <TitleContext.Provider value={titleId}>{children}</TitleContext.Provider>
            </div>
        </div>,
        document.body,
    );
}
