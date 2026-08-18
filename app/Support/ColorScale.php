<?php

namespace App\Support;

class ColorScale
{
    /**
     * Turunkan skala 50-900 (gaya Tailwind) dari 1 warna hex — dipakai
     * sebagai warna "800" (titik acuan), sisanya di-lighten/darken lewat HSL.
     *
     * @return array<string, string> mis. ['50' => '#f0f...', ..., '900' => '#0f...']
     */
    public static function fromHex(string $hex): array
    {
        [$h, $s, $l] = self::hexToHsl($hex);

        // Lightness target per step, "800" = warna asli.
        $steps = [
            '50' => 0.96, '100' => 0.91, '200' => 0.80, '300' => 0.68,
            '400' => 0.56, '500' => 0.46, '600' => 0.38, '700' => 0.30,
            '800' => $l, '900' => max(0.0, $l - 0.10),
        ];

        $scale = [];

        foreach ($steps as $step => $lightness) {
            $scale[$step] = self::hslToHex($h, $s, (float) $lightness);
        }

        return $scale;
    }

    /**
     * @return array{0: float, 1: float, 2: float} [hue 0-360, saturation 0-1, lightness 0-1]
     */
    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '1B4332';
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };

        return [$h * 60, $s, $l];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $l = max(0.0, min(1.0, $l));

        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return sprintf('#%02x%02x%02x', $v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $h /= 360;

        $r = self::hueToRgb($p, $q, $h + 1 / 3);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 1 / 3);

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
