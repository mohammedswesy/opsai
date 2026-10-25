import type { FormHTMLAttributes, HTMLAttributes, ReactNode } from 'react';

const surface = 'rounded-card border border-line/70 bg-surface shadow-card';

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
