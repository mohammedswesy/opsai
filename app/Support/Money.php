<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function normalize(string|int|float $amount): string
    {
        $value = is_string($amount) ? trim($amount) : (string) $amount;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        return self::round($value, 2);
    }

    public static function add(string $left, string $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function sub(string $left, string $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function mul(string $left, string $right): string
    {
        $product = bcmul(self::normalize($left), self::normalize($right), 6);

        return self::round($product, 2);
    }

    public static function percentage(string $amount, string $rate): string
    {
        $fraction = bcdiv(self::normalize($rate), '100', 6);
        $product = bcmul(self::normalize($amount), $fraction, 6);

        return self::round($product, 2);
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }

    public static function round(string $amount, int $scale = 2): string
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $negative = str_starts_with($amount, '-');
        $absolute = ltrim($amount, '-');
        $increment = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($absolute, $increment, $scale);

        return $negative ? '-'.$rounded : $rounded;
    }
}
