<?php

namespace App\Support;

class PhoneNormalizer
{
    /** @var array<string, string> */
    private const CALLING_CODES = [
        'SA' => '966',
        'AE' => '971',
        'QA' => '974',
        'KW' => '965',
        'BH' => '973',
        'OM' => '968',
    ];

    public function normalize(?string $phone, ?string $country = null): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        $code = $this->callingCode($digits);

        if ($code !== null) {
            $national = substr($digits, strlen($code));

            return strlen($national) >= 7 && strlen($national) <= 10 ? $code.$national : null;
        }

        $countryCode = $this->countryCode($country);

        if ($countryCode === null) {
            return null;
        }

        $national = ltrim($digits, '0');

        if (strlen($national) < 7 || strlen($national) > 10) {
            return null;
        }

        return $countryCode.$national;
    }

    /**
     * @return list<string>
     */
    public function candidates(?string $phone, ?string $country = null): array
    {
        $normalized = $this->normalize($phone, $country);

        if ($normalized === null) {
            return [];
        }

        $code = $this->callingCode($normalized) ?? '';
        $national = $code === '' ? $normalized : substr($normalized, strlen($code));

        return array_values(array_unique(array_filter([
            $normalized,
            '+'.$normalized,
            $national,
            '0'.$national,
        ])));
    }

    private function callingCode(string $digits): ?string
    {
        $codes = array_values(self::CALLING_CODES);
        usort($codes, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($codes as $code) {
            if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
                return $code;
            }
        }

        return null;
    }

    private function countryCode(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        return self::CALLING_CODES[strtoupper($country)] ?? null;
    }
}
