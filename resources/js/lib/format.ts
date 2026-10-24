import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useI18n } from '@/i18n';

/*
 * Display formatting for dates, times, money and numbers.
 *
 * - Arabic uses Latin digits (`-u-nu-latn`) so tables, counts, phone numbers and
 *   references all share one digit style. Change ARABIC_LOCALE to flip that.
 * - Instants (Laravel `datetime` casts, ISO strings with Z/offset) are shown in the
 *   organization's timezone.
 * - Calendar dates (Laravel `date` casts, "YYYY-MM-DD") are shown as the same calendar
 *   day everywhere, whatever the timezone.
 * - Times use a 24-hour clock, matching the dispatch board.
 */

const ARABIC_LOCALE = 'ar-u-nu-latn';

export type FormatOptions = { locale: string; timeZone?: string };
export type DateStyle = 'medium' | 'short' | 'long';

type Value = string | Date | null | undefined;

const CALENDAR_DATE = /^(\d{4})-(\d{2})-(\d{2})/;
const WALL_TIME = /^(\d{2}):(\d{2})(?::\d{2})?$/;

function intlLocale(locale: string): string {
    return locale === 'ar' ? ARABIC_LOCALE : 'en';
}

const validZones = new Map<string, boolean>();

/** Returns the zone when Intl accepts it; organization timezones are free text. */
export function safeTimeZone(zone: string | null | undefined): string | undefined {
    if (!zone) {
        return undefined;
    }

    if (!validZones.has(zone)) {
        try {
            new Intl.DateTimeFormat('en', { timeZone: zone });
            validZones.set(zone, true);
        } catch {
            validZones.set(zone, false);
        }
    }

    return validZones.get(zone) ? zone : undefined;
}

function instant(value: Value): Date | null {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

function raw(value: Value): string {
    return value instanceof Date ? value.toISOString() : (value ?? '');
}

const dateStyles: Record<DateStyle, Intl.DateTimeFormatOptions> = {
    medium: { day: 'numeric', month: 'short', year: 'numeric' },
    short: { day: 'numeric', month: 'short' },
    long: { weekday: 'long', day: 'numeric', month: 'long' },
};

const timeStyle: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };

/**
 * Formats a calendar date. Strings are read as their YYYY-MM-DD part, so use this for
 * `date` fields only. A Date object is treated as an instant in the organization timezone.
 */
export function formatDate(value: Value, options: FormatOptions, style: DateStyle = 'medium'): string {
    if (!value) {
        return '';
    }

    if (typeof value === 'string') {
        const match = value.match(CALENDAR_DATE);

        if (!match) {
            return value;
        }

        const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

        return new Intl.DateTimeFormat(intlLocale(options.locale), { ...dateStyles[style], timeZone: 'UTC' }).format(date);
    }

    const date = instant(value);

    return date ? new Intl.DateTimeFormat(intlLocale(options.locale), { ...dateStyles[style], timeZone: options.timeZone }).format(date) : '';
}

/** Formats an instant as date and time in the organization timezone. */
export function formatDateTime(value: Value, options: FormatOptions): string {
    const date = instant(value);

    if (!date) {
        return raw(value);
    }

    return new Intl.DateTimeFormat(intlLocale(options.locale), { ...dateStyles.medium, ...timeStyle, timeZone: options.timeZone }).format(date);
}

/** Formats an instant's clock time, or a wall time such as "09:30" / "09:30:00" as-is. */
export function formatTime(value: Value, options: FormatOptions): string {
    if (typeof value === 'string') {
        const wall = value.match(WALL_TIME);

        if (wall) {
            const date = new Date(Date.UTC(1970, 0, 1, Number(wall[1]), Number(wall[2])));

            return new Intl.DateTimeFormat(intlLocale(options.locale), { ...timeStyle, timeZone: 'UTC' }).format(date);
        }
    }

    const date = instant(value);

    if (!date) {
        return raw(value);
    }

    return new Intl.DateTimeFormat(intlLocale(options.locale), { ...timeStyle, timeZone: options.timeZone }).format(date);
}

/** True when both instants fall on the same calendar day in the organization timezone. */
export function sameDay(left: Value, right: Value, options: FormatOptions): boolean {
    const a = instant(left);
    const b = instant(right);

    if (!a || !b) {
        return false;
    }

    const key = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: options.timeZone });

    return key.format(a) === key.format(b);
}

function zonedParts(date: Date, timeZone: string | undefined): Record<string, number> {
    const parts = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
        timeZone,
    }).formatToParts(date);

    return Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, Number(part.value)]));
}

/** Milliseconds the zone is ahead of UTC at the given instant. */
function zoneOffset(date: Date, timeZone: string | undefined): number {
    const p = zonedParts(date, timeZone);

    return Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute, p.second) - Math.floor(date.getTime() / 1000) * 1000;
}

/**
 * Converts an instant (e.g. "2026-12-15T06:00:00.000000Z") to the value a
 * `datetime-local` input expects, as wall time in the organization timezone.
 */
export function toDateTimeInput(value: Value, timeZone: string | undefined): string {
    const date = instant(value);

    if (!date) {
        return '';
    }

    const p = zonedParts(date, timeZone);
    const pad = (number: number) => String(number).padStart(2, '0');

    return `${p.year}-${pad(p.month)}-${pad(p.day)}T${pad(p.hour)}:${pad(p.minute)}`;
}

/**
 * Converts a `datetime-local` value, read as wall time in the organization timezone,
 * to a UTC ISO string the backend stores without reinterpreting it.
 */
export function fromDateTimeInput(local: string, timeZone: string | undefined): string | null {
    const match = local.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);

    if (!match) {
        return null;
    }

    const wall = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]));
    let utc = wall - zoneOffset(new Date(wall), timeZone);
    // Re-check at the candidate instant so a DST change between the two readings is respected.
    utc = wall - zoneOffset(new Date(utc), timeZone);

    return new Date(utc).toISOString();
}

export function formatMoney(amount: string | number | null | undefined, currency: string | null | undefined, options: FormatOptions): string {
    if (amount === null || amount === undefined || amount === '') {
        return '';
    }

    const value = Number(amount);

    if (!Number.isFinite(value) || !currency) {
        return currency ? `${amount} ${currency}` : String(amount);
    }

    try {
        return new Intl.NumberFormat(intlLocale(options.locale), { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
    } catch {
        return `${amount} ${currency}`;
    }
}

export function formatNumber(value: string | number | null | undefined, options: FormatOptions, digits: Intl.NumberFormatOptions = {}): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const number = Number(value);

    return Number.isFinite(number) ? new Intl.NumberFormat(intlLocale(options.locale), digits).format(number) : String(value);
}

/** Formats a duration given in minutes, e.g. "1 hr, 30 min" / "ساعة و30 دقيقة". */
export function formatDuration(minutes: number | string | null | undefined, options: FormatOptions): string {
    if (minutes === null || minutes === undefined || minutes === '') {
        return '';
    }

    const total = Math.round(Number(minutes));

    if (!Number.isFinite(total)) {
        return String(minutes);
    }

    const locale = intlLocale(options.locale);
    const unitDisplay = options.locale === 'ar' ? 'long' : 'short';
    const hours = Math.floor(total / 60);
    const rest = total % 60;
    const parts = [
        hours > 0 ? new Intl.NumberFormat(locale, { style: 'unit', unit: 'hour', unitDisplay }).format(hours) : null,
        rest > 0 || hours === 0 ? new Intl.NumberFormat(locale, { style: 'unit', unit: 'minute', unitDisplay }).format(rest) : null,
    ].filter((part): part is string => part !== null);

    return new Intl.ListFormat(locale, { style: 'narrow', type: 'unit' }).format(parts);
}

/** Formatters bound to the current locale and the organization timezone. */
export function useFormat() {
    const { locale } = useI18n();
    const { organization } = usePage().props;
    const timeZone = safeTimeZone(organization?.timezone);
    const currency = organization?.currency ?? null;

    return useMemo(() => {
        const options: FormatOptions = { locale, timeZone };

        return {
            locale,
            timeZone,
            currency,
            date: (value: Value, style?: DateStyle) => formatDate(value, options, style),
            dateTime: (value: Value) => formatDateTime(value, options),
            time: (value: Value) => formatTime(value, options),
            sameDay: (left: Value, right: Value) => sameDay(left, right, options),
            money: (amount: string | number | null | undefined, code: string | null | undefined = currency) => formatMoney(amount, code, options),
            number: (value: string | number | null | undefined, digits?: Intl.NumberFormatOptions) => formatNumber(value, options, digits),
            duration: (minutes: number | string | null | undefined) => formatDuration(minutes, options),
        };
    }, [currency, locale, timeZone]);
}
