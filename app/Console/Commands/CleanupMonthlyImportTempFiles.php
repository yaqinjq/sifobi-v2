<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * File upload sementara untuk Import Opname/Spoil Bulanan (lihat
 * OpnameMonthlyImportController/SpoilWasteMonthlyImportController::preview())
 * disimpan di tmp-imports/{tenant_id}/{user_id}/... dan seharusnya sudah
 * dihapus otomatis begitu job apply selesai. Command ini jaring pengaman
 * untuk file yang tertinggal (preview tidak pernah dilanjut ke apply, atau
 * job gagal sebelum sempat menghapus) -- hapus yang lebih tua dari 48 jam.
 */
class CleanupMonthlyImportTempFiles extends Command
{
    protected $signature = 'monthly-import:cleanup-temp-files';

    protected $description = 'Hapus file upload sementara Import Bulanan (Opname/Spoil) yang sudah lebih dari 48 jam';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(48)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('tmp-imports') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Dihapus {$deleted} file upload sementara.");

        return self::SUCCESS;
    }
}
