const warned = new Set<string>();

export function optionLabel(labels: object, value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const map = labels as Record<string, string>;
    const label = map[value];

    if (label === undefined) {
        if (import.meta.env.DEV && !warned.has(value)) {
            warned.add(value);
            console.warn(`[i18n] Missing t.labels entry for "${value}" in resources/js/i18n/{en,ar}.ts`);
        }

        return value;
    }

    return label;
}
