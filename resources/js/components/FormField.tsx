import { cloneElement, isValidElement, useId, type InputHTMLAttributes, type ReactElement, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';

/** Focus border and ring for every text control, including ones with their own sizing. */
export const controlFocusClassName = 'transition-[border-color] duration-180 focus:border-focus focus:outline-2 focus:outline-offset-2 focus:outline-focus';

/** Shared look for text inputs, selects and textareas, including the focus ring. */
export const controlClassName = `min-h-10 w-full rounded-control border border-line/80 bg-surface px-3 text-body text-ink ${controlFocusClassName}`;

type ControlProps = { id?: string; required?: boolean; 'aria-invalid'?: boolean; 'aria-describedby'?: string };

function isControl(type: unknown): boolean {
    return type === 'input' || type === 'select' || type === 'textarea' || type === Input || type === Select || type === Textarea;
}

/**
 * Label, control, hint and error. The label wraps the control, and a single control
 * child also gets an id, `aria-invalid`, `aria-describedby` (hint and error) and
 * `required`, so screen readers announce the error with the field.
 */
export function FormField({
    label,
    error,
    hint,
    required = false,
    children,
}: {
    label: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    children: ReactNode;
}) {
    const id = useId();
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    let control = children;

    if (isValidElement<ControlProps>(children) && isControl(children.type)) {
        const own = children.props;
        control = cloneElement(children as ReactElement<ControlProps>, {
            id: own.id ?? `${id}-control`,
            required: own.required ?? (required || undefined),
            'aria-invalid': error ? true : own['aria-invalid'],
            'aria-describedby': [own['aria-describedby'], describedBy].filter(Boolean).join(' ') || undefined,
        });
    }

    return (
        <label className="block space-y-1.5">
            <span className="text-body font-medium text-ink-body">{label}</span>
            {control}
            {hint ? (
                <span id={hintId} className="block text-caption text-ink-muted">
                    {hint}
                </span>
            ) : null}
            {error ? (
                <span id={errorId} className="text-body text-danger">
                    {error}
                </span>
            ) : null}
        </label>
    );
}

export function Input({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return <input className={`${controlClassName} ${className}`.trim()} {...props} />;
}

export function Select({ className = '', ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return <select className={`${controlClassName} ${className}`.trim()} {...props} />;
}

export function Textarea({ className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea className={`${controlClassName} py-2 ${className}`.trim()} {...props} />;
}

/** Checkbox with its label to the side (inline start). */
export function Checkbox({ label, className = '', ...props }: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { label: ReactNode }) {
    return (
        <label className={`inline-flex items-center gap-2 text-body text-ink-body ${className}`.trim()}>
            <input type="checkbox" className="size-4 rounded-sm border-line-strong accent-brand" {...props} />
            {label}
        </label>
    );
}
