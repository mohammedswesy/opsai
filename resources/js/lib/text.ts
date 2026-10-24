/** Replaces `{name}` placeholders in a translated template. */
export function fill(template: string, values: Record<string, string | number>): string {
    return Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`{${key}}`, String(value)), template);
}
