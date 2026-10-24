import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { useI18n } from '@/i18n';

export type RowAction = {
    key: string;
    label: string;
    href?: string;
    onClick?: () => void;
    tone?: 'danger';
    /** Shown as a visible link/button in the row instead of inside the menu. */
    inline?: boolean;
};

const itemClassName = 'flex w-full items-center rounded-control px-3 py-2 text-start text-body hover:bg-surface-muted focus-visible:bg-surface-muted focus-visible:outline-none';

/**
 * "More actions" button with a menu of secondary row actions. The menu is portalled
 * and fixed-positioned so the table's scroll container cannot clip it, and it lines
 * up with the button's end edge in both directions. The button is described by the
 * row's primary cell (`describedBy`) rather than carrying the record name in its label.
 */
export function RowActionMenu({ actions, describedBy }: { actions: RowAction[]; describedBy?: string }) {
    const { t, dir } = useI18n();
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState<{ top: number; left?: number; right?: number } | null>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const menuId = useId();

    function close(focusButton = true) {
        setOpen(false);
        if (focusButton) {
            buttonRef.current?.focus();
        }
    }

    useLayoutEffect(() => {
        if (!open || !buttonRef.current) {
            return;
        }

        const rect = buttonRef.current.getBoundingClientRect();
        const menuHeight = menuRef.current?.offsetHeight ?? 0;
        const below = rect.bottom + 4;
        const top = below + menuHeight > window.innerHeight - 8 ? Math.max(8, rect.top - menuHeight - 4) : below;

        setPosition(dir === 'rtl' ? { top, left: rect.left } : { top, right: window.innerWidth - rect.right });
    }, [dir, open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        menuRef.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();

        function onPointerDown(event: PointerEvent) {
            const target = event.target as Node;
            if (!menuRef.current?.contains(target) && !buttonRef.current?.contains(target)) {
                setOpen(false);
            }
        }

        function onViewportChange() {
            setOpen(false);
        }

        document.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('scroll', onViewportChange, true);
        window.addEventListener('resize', onViewportChange);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('scroll', onViewportChange, true);
            window.removeEventListener('resize', onViewportChange);
        };
    }, [open]);

    function onMenuKeyDown(event: ReactKeyboardEvent<HTMLDivElement>) {
        const items = [...(menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];
        const index = items.indexOf(document.activeElement as HTMLElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'Tab') {
            setOpen(false);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[(index + 1) % items.length]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            items[(index - 1 + items.length) % items.length]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            items[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            items.at(-1)?.focus();
        }
    }

    return (
        <>
            <button
                ref={buttonRef}
                type="button"
                className="inline-flex size-9 items-center justify-center rounded-control text-ink-muted hover:bg-surface-muted hover:text-ink"
                aria-label={t.list.moreActions}
                aria-describedby={describedBy}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={open ? menuId : undefined}
                onClick={() => setOpen((current) => !current)}
            >
                <MoreHorizontal className="size-4" strokeWidth={1.75} aria-hidden="true" />
            </button>
            {open
                ? createPortal(
                      <div
                          ref={menuRef}
                          id={menuId}
                          role="menu"
                          aria-label={t.list.moreActions}
                          dir={dir}
                          className="fixed z-50 min-w-40 rounded-control border border-line bg-surface p-1 shadow-overlay"
                          style={{ top: position?.top ?? -9999, left: position?.left, right: position?.right }}
                          onKeyDown={onMenuKeyDown}
                      >
                          {actions.map((action) => {
                              const className = `${itemClassName} ${action.tone === 'danger' ? 'text-danger' : 'text-ink'}`;

                              return action.href ? (
                                  <Link key={action.key} role="menuitem" href={action.href} className={className} onClick={() => close(false)}>
                                      {action.label}
                                  </Link>
                              ) : (
                                  <button
                                      key={action.key}
                                      type="button"
                                      role="menuitem"
                                      className={className}
                                      onClick={() => {
                                          close(false);
                                          action.onClick?.();
                                      }}
                                  >
                                      {action.label}
                                  </button>
                              );
                          })}
                      </div>,
                      document.body,
                  )
                : null}
        </>
    );
}
