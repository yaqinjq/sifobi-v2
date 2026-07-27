<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:generate-icons';
    protected $description = 'Generate ikon PNG untuk PWA dan Android (semua ukuran)';

    /** @var array<int, string> */
    private array $androidDensities = [
        48  => 'mipmap-mdpi',
        72  => 'mipmap-hdpi',
        96  => 'mipmap-xhdpi',
        144 => 'mipmap-xxhdpi',
        192 => 'mipmap-xxxhdpi',
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Ekstensi GD tidak tersedia. Install php-gd lalu coba lagi.');

            return self::FAILURE;
        }

        $setting  = \App\Models\AppSetting::current();
        $appName  = $setting?->app_name ?? config('app.name', 'SIFOBI');
        $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $appName) ?: 'SF', 0, 2));

        // ── PWA icons ──────────────────────────────────────────────────────
        $pwaDir = public_path('icons');
        File::ensureDirectoryExists($pwaDir);

        foreach ([192, 512] as $size) {
            $this->makeIcon($pwaDir."/icon-{$size}.png", $size, $initials, false);
        }

        $this->makeIcon($pwaDir.'/icon-maskable-512.png', 512, $initials, true);

        $this->info('PWA icons:');
        $this->line('  public/icons/icon-192.png');
        $this->line('  public/icons/icon-512.png');
        $this->line('  public/icons/icon-maskable-512.png');

        // ── Android icons ──────────────────────────────────────────────────
        $androidRes = base_path('android/app/src/main/res');

        if (File::isDirectory($androidRes)) {
            $this->info('Android icons:');

            foreach ($this->androidDensities as $size => $density) {
                $dir = "{$androidRes}/{$density}";
                File::ensureDirectoryExists($dir);

                $this->makeIcon("{$dir}/ic_launcher.png", $size, $initials, false);
                $this->makeIcon("{$dir}/ic_launcher_round.png", $size, $initials, false, true);
                $this->makeIcon("{$dir}/ic_launcher_foreground.png", $size, $initials, false);

                $this->line("  android/res/{$density}/ic_launcher.png ({$size}px)");
            }
        } else {
            $this->warn('Direktori android/ belum ada. Jalankan "npx cap add android" dulu, lalu jalankan perintah ini lagi.');
        }

        return self::SUCCESS;
    }

    private function makeIcon(string $path, int $size, string $initials, bool $maskable, bool $circular = false): void
    {
        $im = imagecreatetruecolor($size, $size);
        imageantialias($im, true);

        $bg    = imagecolorallocate($im, 27, 67, 50);   // #1B4332
        $white = imagecolorallocate($im, 255, 255, 255);
        $outer = imagecolorallocate($im, 249, 250, 251); // gray-50

        imagefill($im, 0, 0, $circular ? $outer : $bg);

        if ($circular) {
            // Ikon lingkaran (ic_launcher_round)
            imagefilledellipse($im, (int) ($size / 2), (int) ($size / 2), $size, $size, $bg);
        } elseif (! $maskable) {
            // Rounded rectangle
            $r = (int) ($size * 0.18);
            imagefill($im, 0, 0, $outer);
            imagefilledrectangle($im, $r, 0, $size - $r, $size, $bg);
            imagefilledrectangle($im, 0, $r, $size, $size - $r, $bg);
            imagefilledellipse($im, $r, $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $size - $r, $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $r, $size - $r, $r * 2, $r * 2, $bg);
            imagefilledellipse($im, $size - $r, $size - $r, $r * 2, $r * 2, $bg);
        }

        // Teks inisial di tengah
        $font  = 5;
        $fontW = imagefontwidth($font);
        $fontH = imagefontheight($font);
        $scale = max(1, (int) ($size / 96));
        $textW = $fontW * strlen($initials) * $scale;
        $textH = $fontH * $scale;
        $x     = (int) (($size - $textW) / 2);
        $y     = (int) (($size - $textH) / 2);

        for ($i = 0; $i < strlen($initials); $i++) {
            $cx = $x + $i * $fontW * $scale;
            for ($dx = 0; $dx < $scale; $dx++) {
                for ($dy = 0; $dy < $scale; $dy++) {
                    imagestring($im, $font, $cx + $dx, $y + $dy, $initials[$i], $white);
                }
            }
        }

        imagepng($im, $path, 9);
        // PHP 8.x: GdImage objects auto-cleanup, imagedestroy() is a no-op
    }
}
