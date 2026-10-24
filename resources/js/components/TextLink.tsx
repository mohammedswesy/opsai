import { Link, type InertiaLinkProps } from '@inertiajs/react';
import type { AnchorHTMLAttributes } from 'react';

const linkClassName = 'font-medium text-brand underline-offset-2 hover:text-brand-strong hover:underline';

export function TextLink({ className = '', ...props }: InertiaLinkProps) {
    return <Link className={`${linkClassName} ${className}`.trim()} {...props} />;
}

/** Plain anchor (tel:, mailto:) with the same look as TextLink. */
export function ExternalTextLink({ className = '', ...props }: AnchorHTMLAttributes<HTMLAnchorElement>) {
    return <a className={`${linkClassName} ${className}`.trim()} {...props} />;
}
