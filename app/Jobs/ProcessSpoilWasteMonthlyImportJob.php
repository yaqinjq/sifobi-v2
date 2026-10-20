<?php

namespace App\Jobs;

use App\Imports\SpoilWasteMonthlyImport;
use App\Models\MonthlyImportRun;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Mirrors ProcessOpnameMonthlyImportJob's background+sequential pattern --
 * lihat docblock di sana. Sel diproses ASCENDING by tanggal karena
 * approve()'s pengecekan saldo live bergantung pada mutasi tanggal
 * sebelumnya sudah ter-posting.
 */
class ProcessSpoilWasteMonthlyImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(SpoilWasteService $service, StockLedgerService $ledger): void
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
            $selectedCellKeys = array_flip($scope['selected_cell_keys'] ?? []);

            $import = new SpoilWasteMonthlyImport();
            Excel::import($import, Storage::disk('local')->path($run->upload_path));

            $cells = collect($import->preview((int) $run->tenant_id, $outletId, $departmentId))
                ->filter(fn ($cell) => isset($selectedCellKeys["{$cell['date']}:{$cell['item_id']}"]))
                ->sortBy('date')
                ->values();

            $run->forceFill(['total_units' => $cells->count()])->save();

            $errors = [];
            $processed = 0;
            $failed = 0;

            foreach ($cells as $cell) {
                try {
                    $import->processCell(
                        $cell,
                        (int) $run->tenant_id,
                        $outletId,
                        $departmentId,
                        (int) $run->created_by,
                        $service,
                        $ledger,
                    );
                    $processed++;
                } catch (Throwable $throwable) {
                    $failed++;
                    $errors[] = "{$cell['date']} - {$cell['item_name']}: {$throwable->getMessage()}";
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
