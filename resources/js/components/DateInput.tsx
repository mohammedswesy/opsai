import { useId, type InputHTMLAttributes } from 'react';
import { controlClassName } from '@/components/FormField';
import { useFormat } from '@/lib/format';

// The native date text follows the browser locale, not the app's. It is hidden and the
// formatted date is drawn on top, so Arabic and English both read naturally.
const hideNativeText =
    'text-transparent caret-transparent [&::-webkit-datetime-edit]:text-transparent [&::-webkit-datetime-edit-fields-wrapper]:text-transparent [&::-webkit-datetime-edit-text]:text-transparent [&::-webkit-datetime-edit-month-field]:text-transparent [&::-webkit-datetime-edit-day-field]:text-transparent [&::-webkit-datetime-edit-year-field]:text-transparent';

/**
 * Date input that shows the value in the app's locale and the organization timezone.
 * The visible date is linked with `aria-describedby`, so screen readers hear it too.
 */
export function DateInput({
    value,
    placeholder,
    className = '',
    ...props
}: Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value' | 'defaultValue'> & { value: string; placeholder: string }) {
    const format = useFormat();
    const displayId = useId();

    return (
        <span className="relative block">
            <input className={`${controlClassName} ${hideNativeText} ${className}`.trim()} type="date" defaultValue={value} aria-describedby={displayId} {...props} />
            <span id={displayId} className="pointer-events-none absolute inset-y-0 start-3 end-9 flex items-center truncate text-body text-ink">
                {value ? format.date(value) : <span className="text-ink-muted">{placeholder}</span>}
            </span>
        </span>
    );
}
