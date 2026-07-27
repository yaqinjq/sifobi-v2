<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:generate-icons';
    protected $description = 'Generate ikon PNG untuk PWA (192x192 dan 512x512)';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Ekstensi GD tidak tersedia. Install php-gd lalu coba lagi.');
            return self::FAILURE;
        }

        $dir = public_path('icons');
        File::ensureDirectoryExists($dir);

        $setting   = \App\Models\AppSetting::current();
        $appName   = $setting?->app_name ?? config('app.name', 'SIFOBI');
        $initials  = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $appName) ?: 'SF', 0, 2));

        foreach ([192, 512] as $size) {
            $this->makeIcon($dir, $size, $initials, false);
        }

        // Maskable: padding 20% agar ikon tetap terlihat saat di-crop oleh OS
        $this->makeIcon($dir, 512, $initials, true, 'icon-maskable-512.png');

        $this->info('Ikon PWA berhasil dibuat di ' . $dir);
        $this->line('  - icons/icon-192.png');
        $this->line('  - icons/icon-512.png');
        $this->line('  - icons/icon-maskable-512.png');

        return self::SUCCESS;
    }

    private function makeIcon(string $dir, int $size, string $initials, bool $maskable, ?string $filename = null): void
    {
        $im = imagecreatetruecolor($size, $size);
        imageantialias($im, true);

        // Warna utama app: #1B4332 (primary-800)
        $bg    = imagecolorallocate($im, 27, 67, 50);
        $fg    = imagecolorallocate($im, 255, 255, 255);
        $light = imagecolorallocate($im, 45, 106, 79); // primary-700

        // Background
        imagefill($im, 0, 0, $bg);

        // Rounded corner effect (simulasi dengan lingkaran di sudut)
        $radius = (int) ($size * 0.18);
        $this->roundedRect($im, 0, 0, $size, $size, $radius, $bg, $fg, $light, $maskable);

        // Teks inisial — gunakan built-in font GD (font 5 = 9x15px)
        $fontW = imagefontwidth(5);
        $fontH = imagefontheight(5);
        $scale = (int) ($size / 96); // scale font berdasarkan ukuran ikon
        $scale = max(1, $scale);

        $textLen   = strlen($initials);
        $totalW    = $fontW * $textLen * $scale;
        $totalH    = $fontH * $scale;
        $x         = (int) (($size - $totalW) / 2);
        $y         = (int) (($size - $totalH) / 2);

        // Gambar teks dengan scaling manual (tulis piksel per piksel)
        $this->drawScaledText($im, $initials, $x, $y, $scale, $fg);

        $outFile = $filename ?? "icon-{$size}.png";
        imagepng($im, $dir . '/' . $outFile, 9);
        imagedestroy($im);
    }

    private function roundedRect($im, int $x, int $y, int $w, int $h, int $r, $bg, $fg, $accent, bool $maskable): void
    {
        // Warnai ulang sudut-sudut dengan warna transparan (hapus sudut)
        $transparent = imagecolorallocate($im, 0, 1, 0);

        // Fill sudut dengan warna luar (simulasi rounded corner)
        $corners = [
            [$x, $y],
            [$x + $w - $r, $y],
            [$x, $y + $h - $r],
            [$x + $w - $r, $y + $h - $r],
        ];

        if (! $maskable) {
            foreach ($corners as [$cx, $cy]) {
                imagefilledrectangle($im, $cx, $cy, $cx + $r, $cy + $r, $bg);
            }

            $bgOuter = imagecolorallocate($im, 249, 250, 251); // gray-50
            imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $bg);

            // Bersihkan sudut (warna abu di luar radius)
            imagefilledrectangle($im, $x, $y, $x + $r - 1, $y + $r - 1, $bgOuter);
            imagefilledrectangle($im, $x + $w - $r + 1, $y, $x + $w, $y + $r - 1, $bgOuter);
            imagefilledrectangle($im, $x, $y + $h - $r + 1, $x + $r - 1, $y + $h, $bgOuter);
            imagefilledrectangle($im, $x + $w - $r + 1, $y + $h - $r + 1, $x + $w, $y + $h, $bgOuter);

            // Gambar ulang sudut bundar yang benar
            imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $bg);
        }
    }

    private function drawScaledText($im, string $text, int $x, int $y, int $scale, $color): void
    {
        foreach (str_split($text) as $i => $char) {
            $charX = $x + $i * imagefontwidth(5) * $scale;
            imagestring($im, 5, $charX, $y, $char, $color);

            // Scale up: gambar karakter berulang untuk efek tebal/besar
            for ($dx = 0; $dx < $scale; $dx++) {
                for ($dy = 0; $dy < $scale; $dy++) {
                    imagestring($im, 5, $charX + $dx, $y + $dy, $char, $color);
                }
            }
        }
    }
}
