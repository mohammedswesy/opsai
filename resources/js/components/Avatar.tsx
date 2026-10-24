const tones = [
    'bg-brand-soft text-brand-strong',
    'bg-success-soft text-success',
    'bg-warning-soft text-warning',
    'bg-info-soft text-info',
    'bg-danger-soft text-danger',
];

const sizes = { sm: 'size-8 text-caption', md: 'size-9 text-body' };

function initialOf(name: string): string {
    const first = [...name.trim()][0] ?? '·';

    return first.toLocaleUpperCase();
}

/** Stable tone for a name, so the same organization or person always gets the same colour. */
function toneFor(name: string): string {
    const hash = [...name].reduce((sum, char) => (sum * 31 + char.codePointAt(0)!) >>> 0, 7);

    return tones[hash % tones.length];
}

/**
 * Initial-letter avatar on the semantic tones. `shape="square"` is used for
 * organizations, round for people; `tone="brand"` gives the solid brand fill.
 */
export function Avatar({ name, size = 'md', shape = 'round', tone = 'auto' }: { name: string; size?: keyof typeof sizes; shape?: 'round' | 'square'; tone?: 'auto' | 'brand' }) {
    const colour = tone === 'brand' ? 'bg-brand text-on-brand' : toneFor(name);

    return (
        <span aria-hidden="true" className={`flex shrink-0 items-center justify-center font-semibold ${sizes[size]} ${shape === 'square' ? 'rounded-control' : 'rounded-badge'} ${colour}`}>
            {initialOf(name)}
        </span>
    );
}
