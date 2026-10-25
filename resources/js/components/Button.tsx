import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

const variants = {
    primary: 'border border-transparent bg-brand bg-brand-gradient text-on-brand shadow-control hover:brightness-96',
    secondary: 'border border-line/80 bg-surface text-ink-body shadow-control hover:bg-surface-muted',
    ghost: 'border border-transparent bg-transparent text-ink-secondary hover:bg-surface-muted/80',
    danger: 'border border-transparent bg-danger text-on-brand shadow-control hover:brightness-90',
};

const sizes = {
    md: 'min-h-10 px-3',
    sm: 'min-h-8 px-2.5',
};

const iconSizes = {
    md: 'size-10',
    sm: 'size-8',
};

export type ButtonVariant = keyof typeof variants;
export type ButtonSize = keyof typeof sizes;

const base = 'inline-flex items-center justify-center gap-1.5 rounded-control text-body font-medium transition-[background-color,box-shadow,transform,color,filter] duration-200 ease-out';

export function buttonClassName(variant: ButtonVariant = 'secondary', className = '', size: ButtonSize = 'md'): string {
    return `${base} ${sizes[size]} ${variants[variant]} ${className}`.trim();
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    /** Disables the button and shows a spinner; the label stays so the width does not jump. */
    loading?: boolean;
};

export function Button({ variant = 'secondary', size = 'md', loading = false, className = '', type = 'button', disabled, children, ...props }: ButtonProps) {
    return (
        <button type={type} className={buttonClassName(variant, className, size)} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
            {loading ? <Loader2 className="size-4 animate-spin" strokeWidth={2} aria-hidden="true" /> : null}
            {children}
        </button>
    );
}

export function ButtonLink({ variant = 'primary', size = 'md', className = '', ...props }: Omit<InertiaLinkProps, 'size'> & { variant?: ButtonVariant; size?: ButtonSize }) {
    return <Link className={buttonClassName(variant, className, size)} {...props} />;
}

/** Square icon-only button. `label` is required: it is the accessible name and the tooltip. */
export function IconButton({
    label,
    variant = 'ghost',
    size = 'md',
    className = '',
    type = 'button',
    children,
    ...props
}: Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'aria-label' | 'children'> & { label: string; variant?: ButtonVariant; size?: ButtonSize; children: ReactNode }) {
    return (
        <button type={type} aria-label={label} title={label} className={`${base} ${iconSizes[size]} ${variants[variant]} ${className}`.trim()} {...props}>
            {children}
        </button>
    );
}
