<?php

namespace App\Support;

use Closure;
use InvalidArgumentException;

class Decimal
{
    public static function normalize(mixed $value, int $scale = 6): string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException('Decimal value is required.');
        }

        if (str_contains($value, '.') && str_contains($value, ',')) {
            throw new InvalidArgumentException('Thousands separators are not allowed.');
        }

        if (! preg_match('/^\d+([.,]\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal value is invalid.');
        }

        $normalized = str_replace(',', '.', $value);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        if ($fraction === '') {
            return $whole;
        }

        return $whole.'.'.substr($fraction, 0, $scale);
    }

    public static function toFixed(mixed $value, int $scale = 6): string
    {
        $normalized = self::normalize($value, $scale);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return $whole.'.'.str_pad(substr($fraction, 0, $scale), $scale, '0');
    }

    public static function validationRule(int $scale = 6): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scale): void {
            try {
                self::normalize($value, $scale);
            } catch (InvalidArgumentException) {
                $fail("The {$attribute} field must be a valid decimal without thousands separators.");
            }
        };
    }
}

