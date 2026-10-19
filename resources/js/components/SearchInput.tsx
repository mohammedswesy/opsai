import { controlClassName } from '@/components/FormField';

export function SearchInput({
    name = 'search',
    defaultValue,
    placeholder,
}: {
    name?: string;
    defaultValue?: string;
    placeholder: string;
}) {
    return <input className={controlClassName} name={name} defaultValue={defaultValue ?? ''} placeholder={placeholder} />;
}
