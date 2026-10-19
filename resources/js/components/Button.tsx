import { Link, type InertiaLinkProps } from '@inertiajs/react';
import type { ButtonHTMLAttributes } from 'react';

const variants = {
    primary: 'border border-transparent bg-teal-800 text-white shadow-[0_1px_2px_rgba(16,24,40,0.08)] hover:bg-teal-700',
    secondary: 'border border-zinc-200/80 bg-white text-zinc-700 shadow-[0_1px_2px_rgba(16,24,40,0.04)] hover:bg-zinc-50',
    ghost: 'border border-transparent bg-transparent text-zinc-600 hover:bg-zinc-100/80',
    danger: 'border border-transparent bg-red-600 text-white hover:bg-red-700',
};

export function buttonClassName(variant: keyof typeof variants = 'secondary', className = ''): string {
    return `inline-flex min-h-10 items-center justify-center gap-1.5 rounded-[10px] px-3 text-sm font-medium transition-[background-color,box-shadow,transform,color] duration-200 ease-out ${variants[variant]} ${className}`.trim();
}

export function Button({
    variant = 'secondary',
    className = '',
    type = 'button',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: keyof typeof variants }) {
    return <button type={type} className={buttonClassName(variant, className)} {...props} />;
}

export function ButtonLink({
    variant = 'primary',
    className = '',
    ...props
}: InertiaLinkProps & { variant?: keyof typeof variants }) {
    return <Link className={buttonClassName(variant, className)} {...props} />;
}
