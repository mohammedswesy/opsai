import type { ReactNode } from 'react';

/**
 * Centred column for form pages. Wrap the PageHeader and the form card together so the
 * title lines up with the card's start edge in both RTL and LTR. `narrow` suits
 * single-column forms, `wide` two-column forms or forms with line items. On small
 * screens it is full width inside the shell's normal side padding.
 */
export function FormPage({ size = 'narrow', className = '', children }: { size?: 'narrow' | 'wide'; className?: string; children: ReactNode }) {
    return <div className={`mx-auto w-full ${size === 'wide' ? 'max-w-form-wide' : 'max-w-form'} ${className}`.trim()}>{children}</div>;
}
