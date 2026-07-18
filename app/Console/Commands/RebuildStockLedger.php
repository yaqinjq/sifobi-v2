<?php

namespace App\Console\Commands;

use App\Modules\Operations\Models\OpenStock;
use App\Services\StockLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildStockLedger extends Command
{
    protected $signature = 'rebuild:stock-ledger
                            {--dry-run : Preview tanpa eksekusi}
                            {--outlet= : Proses hanya 1 outlet (by ID)}
                            {--force : Jalankan meski sudah ada mutations}';

    protected $description = 'Rebuild stock_mutations dan stock_balances dari POSTED open_stocks yang belum punya mutation_id';

    public function __construct(private readonly StockLedgerService $stockLedgerService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $outletId = $this->option('outlet') ? (int) $this->option('outlet') : null;

        if ($isDryRun) {
            $this->warn('=== DRY RUN — tidak ada yang diubah ===');
        }

        // STEP 1: Cek kondisi awal
        $mutationCount = DB::table('stock_mutations')->count();
        $balanceCount  = DB::table('stock_balances')->count();

        $this->info("stock_mutations saat ini : {$mutationCount}");
        $this->info("stock_balances saat ini  : {$balanceCount}");

        if ($mutationCount > 0 && ! $this->option('force')) {
            $this->error('stock_mutations tidak kosong!');
            $this->error('Gunakan --force jika yakin ingin rebuild ulang (tidak akan hapus data lama).');
            return self::FAILURE;
        }

        // STEP 2: Ambil semua POSTED open_stocks yang belum punya mutation_id
        $query = OpenStock::query()
            ->with(['item'])
            ->where('status', OpenStock::STATUS_POSTED)
            ->whereNull('mutation_id')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderBy('business_date')
            ->orderBy('id');

        $total = $query->count();
        $this->info("Open stock POSTED tanpa mutation_id: {$total}");

        if ($total === 0) {
            $this->info('Tidak ada yang perlu diproses.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $sample = $query->take(10)->get();
            $this->table(
                ['ID', 'Outlet ID', 'Item ID', 'Qty Base', 'Target', 'Business Date'],
                $sample->map(fn (OpenStock $r) => [
                    $r->id,
                    $r->outlet_id,
                    $r->item_id,
                    $r->qty_in_base_unit,
                    $r->stock_target,
                    $r->business_date?->format('Y-m-d') ?? '-',
                ])->toArray()
            );
            if ($total > 10) {
                $this->line('... dan ' . ($total - 10) . ' lainnya');
            }
            $this->newLine();
            $this->info("DRY RUN selesai. Jalankan tanpa --dry-run untuk eksekusi.");
            return self::SUCCESS;
        }

        // STEP 3: Proses tiap open_stock via StockLedgerService
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $skipped  = 0;
        $errors   = 0;

        $query->chunkById(50, function ($openStocks) use ($bar, &$inserted, &$skipped, &$errors): void {
            foreach ($openStocks as $openStock) {
                $this->processOne($openStock, $inserted, $skipped, $errors);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        // STEP 4: Laporan final
        $finalMutations = DB::table('stock_mutations')->count();
        $finalBalances  = DB::table('stock_balances')->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Nilai'],
            [
                ['Mutations dibuat',  $inserted],
                ['Dilewati (skip)',   $skipped],
                ['Errors',            $errors],
                ['stock_mutations',   $finalMutations],
                ['stock_balances',    $finalBalances],
            ]
        );

        if ($errors > 0) {
            $this->warn("Ada {$errors} error — cek storage/logs/laravel.log untuk detail.");
            return self::FAILURE;
        }

        $this->info('Selesai! Semua open stock berhasil di-rebuild.');
        return self::SUCCESS;
    }

    private function processOne(OpenStock $openStock, int &$inserted, int &$skipped, int &$errors): void
    {
        $item = $openStock->item;
        if (! $item) {
            $this->newLine();
            $this->warn("  skip open_stock#{$openStock->id}: item tidak ditemukan");
            $skipped++;
            return;
        }

        $unitId = (int) ($item->base_unit_id ?: $item->inventory_unit_id);
        if (! $unitId) {
            $this->newLine();
            $this->warn("  skip open_stock#{$openStock->id}: item#{$item->id} tidak punya base_unit_id");
            $skipped++;
            return;
        }

        $qtyBase     = (string) $openStock->qty_in_base_unit;
        $performedAt = $openStock->posted_at ?? Carbon::parse($openStock->business_date);
        $performedBy = $openStock->posted_by ?? $openStock->created_by;

        try {
            $mutation = $this->stockLedgerService->openStock([
                'tenant_id'      => $openStock->tenant_id,
                'outlet_id'      => $openStock->outlet_id,
                'item_id'        => $openStock->item_id,
                'unit_id'        => $unitId,
                'stock_target'   => $openStock->stock_target,
                'qty'            => $qtyBase,
                'reference_type' => OpenStock::class,
                'reference_id'   => $openStock->id,
                'performed_by'   => $performedBy,
                'performed_at'   => $performedAt,
                'notes'          => "Rebuild dari open_stock#{$openStock->id}",
                'metadata'       => array_filter([
                    'qty_whole'     => (string) $openStock->qty_whole,
                    'qty_loose'     => (string) $openStock->qty_loose,
                    'cost_per_unit' => $openStock->cost_per_unit !== null ? (string) $openStock->cost_per_unit : null,
                ], fn ($v) => $v !== null),
            ]);

            DB::table('open_stocks')
                ->where('id', $openStock->id)
                ->update(['mutation_id' => $mutation->id]);

            $inserted++;
        } catch (\Throwable $e) {
            Log::error("RebuildStockLedger open_stock#{$openStock->id}: " . $e->getMessage());
            $this->newLine();
            $this->error("  error open_stock#{$openStock->id}: " . $e->getMessage());
            $errors++;
        }
    }
}
