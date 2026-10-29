<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\HtmlString;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use NumberFormatter;

/**
 * Server-side twin of resources/js/lib/format.ts, for output rendered outside the
 * browser (PDFs). Arabic uses Latin digits, calendar dates keep their day in every
 * timezone, instants are shown in the organization timezone, and times use 24 hours.
 */
final class LocaleFormat
{
    private readonly string $icuLocale;

    public function __construct(
        public readonly string $locale,
        private readonly string $timezone,
    ) {
        $this->icuLocale = $locale === 'ar' ? 'ar@numbers=latn' : 'en';
    }

    public function money(string|int|float|null $amount, ?string $currency): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        if ($currency === null || $currency === '') {
            return (string) $amount;
        }

        $formatter = new NumberFormatter($this->icuLocale, NumberFormatter::CURRENCY);
        if ($this->locale === 'ar') {
            // ICU versions disagree on the Arabic order; pin the browser's (ICU 76) "575.00 ر.س.".
            $formatter->setPattern("#,##0.00\u{00A0}¤");
        }
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $formatted = $formatter->formatCurrency((float) $amount, $currency);

        if ($formatted === false) {
            return "{$amount} {$currency}";
        }

        // The Arabic symbol already ends with a right-to-left mark; lead with one too, like the browser.
        return $this->locale === 'ar' ? "\u{200F}{$formatted}" : $formatted;
    }

    /**
     * Escaped user text isolated in its own direction (taken from its first strong letter),
     * so an English line inside an Arabic invoice, or the reverse, keeps its word order.
     */
    public function isolate(?string $text, ?string $dir = null): HtmlString
    {
        $text ??= '';
        $dir ??= preg_match('/^[^\p{L}]*[\p{Arabic}\p{Hebrew}]/u', $text) === 1 ? 'rtl' : 'ltr';

        return new HtmlString('<bdi dir="'.$dir.'">'.e($text).'</bdi>');
    }

    public function number(string|int|float|null $value, int $maxFractionDigits = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatter = new NumberFormatter($this->icuLocale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxFractionDigits);

        return (string) $formatter->format((float) $value);
    }

    /** A `date` field: the stored calendar day, whatever the timezone. */
    public function date(?DateTimeInterface $value): string
    {
        return $value === null ? '' : $this->pattern('yMMMd', 'UTC', $value->format('Y-m-d').' 00:00:00');
    }

    /** A `datetime` field: the instant in the organization timezone. */
    public function dateTime(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        // Date and time are joined by hand: older ICU uses a Latin comma in Arabic, the browser "،".
        $separator = $this->locale === 'ar' ? '، ' : ', ';

        return $this->pattern('yMMMd', $this->timezone, $value).$separator.$this->pattern('HHmm', $this->timezone, $value);
    }

    /** The calendar day of an instant in the organization timezone. */
    public function day(DateTimeInterface $value): string
    {
        return $this->pattern('yMMMd', $this->timezone, $value);
    }

    /** The 24-hour time of an instant in the organization timezone. */
    public function time(DateTimeInterface $value): string
    {
        return $this->pattern('HHmm', $this->timezone, $value);
    }

    private function pattern(string $skeleton, string $timezone, DateTimeInterface|string $value): string
    {
        $pattern = (new IntlDatePatternGenerator($this->icuLocale))->getBestPattern($skeleton);
        $formatter = new IntlDateFormatter($this->icuLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, IntlDateFormatter::GREGORIAN, $pattern);
        $instant = is_string($value) ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : $value;

        return (string) $formatter->format($instant);
    }
}
