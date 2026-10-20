<?php

namespace App\Jobs;

use App\Imports\OpnameMonthlyImport;
use App\Models\MonthlyImportRun;
use App\Services\OpnameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Jalan di background (bukan sinkron dalam request HTTP) karena 1 bulan
 * bisa berarti puluhan sesi Opname x ratusan item -- ribuan query/transaksi
 * kalau dipaksa dalam 1 request. Tetap SEKUENSIAL (bukan Bus::batch() yang
 * paralel) karena urutan tanggal ascending WAJIB untuk kebenaran saldo live
 * yang dibaca approve() -- lihat OpnameMonthlyImport::processDate().
 */
class ProcessOpnameMonthlyImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(OpnameService $service): void
    {
        $run = MonthlyImportRun::query()->find($this->runId);

        if (! $run) {
            return;
        }

        $run->forceFill(['status' => MonthlyImportRun::STATUS_PROCESSING])->save();

        try {
            $scope = $run->scope;
            $outletId = (int) $scope['outlet_id'];
            $departmentId = isset($scope['department_id']) ? (int) $scope['department_id'] : null;
            $selectedDates = $scope['selected_dates'] ?? [];

            $import = new OpnameMonthlyImport();
            Excel::import($import, Storage::disk('local')->path($run->upload_path));

            $preview = collect($import->preview((int) $run->tenant_id, $outletId, $departmentId))
                ->whereIn('date', $selectedDates)
                ->sortBy('date')
                ->values();

            $run->forceFill(['total_units' => $preview->count()])->save();

            $errors = [];
            $processed = 0;
            $failed = 0;

            foreach ($preview as $dateRow) {
                try {
                    $import->processDate(
                        $dateRow['date'],
                        $dateRow['items'],
                        (int) $run->tenant_id,
                        $outletId,
                        $departmentId,
                        (int) $run->created_by,
                        $service,
                    );
                    $processed++;
                } catch (Throwable $throwable) {
                    $failed++;
                    $errors[] = "{$dateRow['date']}: {$throwable->getMessage()}";
                }

                $run->forceFill([
                    'processed_units' => $processed,
                    'failed_units' => $failed,
                    'errors' => $errors,
                ])->save();
            }

            $run->forceFill([
                'status' => MonthlyImportRun::STATUS_DONE,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $throwable) {
            $run->forceFill([
                'status' => MonthlyImportRun::STATUS_FAILED,
                'errors' => array_merge($run->errors ?? [], [$throwable->getMessage()]),
                'finished_at' => now(),
            ])->save();
        } finally {
            Storage::disk('local')->delete($run->upload_path);
        }
    }
}
