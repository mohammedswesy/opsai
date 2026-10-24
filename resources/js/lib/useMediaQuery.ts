import { useSyncExternalStore } from 'react';

/** Tailwind's `md` breakpoint (48rem). */
export const DESKTOP_QUERY = '(min-width: 48rem)';

/**
 * Tracks a media query. Lists use it to render either the table or the card layout,
 * never both, so each record appears in the DOM once.
 */
export function useMediaQuery(query: string): boolean {
    return useSyncExternalStore(
        (notify) => {
            const list = window.matchMedia(query);
            list.addEventListener('change', notify);

            return () => list.removeEventListener('change', notify);
        },
        () => window.matchMedia(query).matches,
        () => true,
    );
}
