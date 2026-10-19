import type { FormHTMLAttributes, HTMLAttributes, ReactNode } from 'react';

const surface = 'rounded-2xl border border-zinc-200/70 bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_8px_24px_-8px_rgba(16,24,40,0.08)]';

export function Card({ children, className = '', ...props }: HTMLAttributes<HTMLDivElement> & { children: ReactNode }) {
    return (
        <div className={`${surface} ${className}`.trim()} {...props}>
            {children}
        </div>
    );
}

export function CardForm({ children, className = '', ...props }: FormHTMLAttributes<HTMLFormElement> & { children: ReactNode }) {
    return (
        <form className={`${surface} ${className}`.trim()} {...props}>
            {children}
        </form>
    );
}
