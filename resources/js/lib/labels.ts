export function optionLabel(labels: object, value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const map = labels as Record<string, string>;

    return map[value] ?? value;
}
