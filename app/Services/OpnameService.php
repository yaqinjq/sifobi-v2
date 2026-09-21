<?php

namespace App\Services;

use App\Modules\Core\Models\Outlet;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\OpnameItem;
use App\Modules\Operations\Models\OpnameSession;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Notifications\WorkflowNotification;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OpnameService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly NotificationRecipientResolver $recipients = new NotificationRecipientResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startSession(array $data, int $userId): OpnameSession
    {
        return DB::transaction(function () use ($data, $userId): OpnameSession {
            $tenantId = (int) $data['tenant_id'];
            $outletId = (int) $data['outlet_id'];
            $departmentId = isset($data['department_id']) && $data['department_id'] !== null
                ? (int) $data['department_id']
                : null;
            $opnameDate = Carbon::parse($data['opname_date'])->toDateString();
            $type = $data['type'] ?? OpnameSession::TYPE_DAILY;

            $this->assertOutletBelongsToTenant($outletId, $tenantId);

            // department_id ikut jadi bagian dari kunci unik sesi -- ini yang
            // memungkinkan BAR dan KITCHEN sama-sama punya sesi opname harian
            // berjalan (DRAFT/SUBMITTED) di outlet & tanggal yang sama tanpa
            // saling memblokir/menumpuk seolah-olah opname diulang berkali-kali.
            $openSessionExists = OpnameSession::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->whereDate('opname_date', $opnameDate)
                ->where('type', $type)
                ->where('department_id', $departmentId)
                ->whereIn('status', [OpnameSession::STATUS_DRAFT, OpnameSession::STATUS_SUBMITTED])
                ->exists();

            if ($openSessionExists) {
                throw ValidationException::withMessages([
                    'opname_date' => 'Masih ada sesi opname draft/submitted untuk outlet, departemen, dan tanggal ini.',
                ]);
            }

            $session = OpnameSession::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'department_id' => $departmentId,
                'type' => $type,
                'opname_type' => $type,
                'opname_date' => $opnameDate,
                'business_date' => $opnameDate,
                'shift' => $data['shift'] ?? null,
                'status' => OpnameSession::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'started_by' => $userId,
            ]);

            foreach ($this->itemsForOpname($tenantId, $outletId, $type, $departmentId) as $item) {
                $systemQty = $this->systemQty($tenantId, $outletId, (int) $item->id);

                // Sesi sudah di-scope ke satu departemen -- item yang masuk ke
                // sini sudah pasti milik departemen tsb (lihat itemsForOpname()),
                // jadi cukup satu baris per item, tidak perlu pecah per departemen.
                if ($departmentId) {
                    $session->items()->create([
                        'tenant_id'          => $tenantId,
                        'item_id'            => $item->id,
                        'unit_id'            => $item->inventory_unit_id ?: $item->base_unit_id,
                        'department_id'      => $departmentId,
                        'system_qty'         => $systemQty,
                        'system_qty_base'    => $systemQty,
                        'counted_qty'        => '0.000000',
                        'physical_qty_whole' => '0.000000',
                        'physical_qty_loose' => '0.000000',
                        'physical_qty_base'  => '0.000000',
                        'variance_qty'       => $systemQty,
                        'variance'           => $systemQty,
                        'variance_value'     => '0.0000',
                        'is_counted'         => false,
                    ]);

                    continue;
                }

                $departments = $item->relationLoaded('departments') ? $item->departments : collect();

                if ($item->primaryDepartment) {
                    $departments = $departments->push($item->primaryDepartment)->unique('id');
                }

                // Sesi lintas-departemen (department_id null, mis. opname
                // historis/bulanan seluruh outlet) -- pertahankan perilaku lama:
                // item dipakai lebih dari satu departemen -> buat baris per departemen.
                if ($departments->count() > 1) {
                    foreach ($departments as $dept) {
                        $session->items()->create([
                            'tenant_id'          => $tenantId,
                            'item_id'            => $item->id,
                            'unit_id'            => $item->inventory_unit_id ?: $item->base_unit_id,
                            'department_id'      => $dept->id,
                            'system_qty'         => $systemQty,
                            'system_qty_base'    => $systemQty,
                            'counted_qty'        => '0.000000',
                            'physical_qty_whole' => '0.000000',
                            'physical_qty_loose' => '0.000000',
                            'physical_qty_base'  => '0.000000',
                            'variance_qty'       => $systemQty,
                            'variance'           => $systemQty,
                            'variance_value'     => '0.0000',
                            'is_counted'         => false,
                        ]);
                    }
                } else {
                    // Item satu atau tanpa departemen — satu baris seperti sebelumnya
                    $session->items()->create([
                        'tenant_id'          => $tenantId,
                        'item_id'            => $item->id,
                        'unit_id'            => $item->inventory_unit_id ?: $item->base_unit_id,
                        'department_id'      => $item->primary_department_id,
                        'system_qty'         => $systemQty,
                        'system_qty_base'    => $systemQty,
                        'counted_qty'        => '0.000000',
                        'physical_qty_whole' => '0.000000',
                        'physical_qty_loose' => '0.000000',
                        'physical_qty_base'  => '0.000000',
                        'variance_qty'       => $systemQty,
                        'variance'           => $systemQty,
                        'variance_value'     => '0.0000',
                        'is_counted'         => false,
                    ]);
                }
            }

            return $session->load(['outlet', 'department', 'items.item.inventoryUnit', 'items.item.baseUnit']);
        });
    }

    public function updateItem(OpnameItem $opnameItem, mixed $wholeQty, mixed $looseQty): OpnameItem
    {
        return DB::transaction(function () use ($opnameItem, $wholeQty, $looseQty): OpnameItem {
            $opnameItem = OpnameItem::query()
                ->with(['session', 'item'])
                ->lockForUpdate()
                ->findOrFail($opnameItem->id);

            if ($opnameItem->session->status !== OpnameSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Item hanya bisa diubah saat sesi masih draft.',
                ]);
            }

            $whole = Decimal::toFixed($wholeQty ?? 0, 6);
            $loose = Decimal::toFixed($looseQty ?? 0, 6);
            $physicalBase = $this->physicalBaseQty($opnameItem->item, $whole, $loose);
            $variance = bcsub((string) $opnameItem->system_qty_base, $physicalBase, 6);
            $cost = Decimal::toFixed($opnameItem->item?->standard_cost ?: $opnameItem->item?->last_purchase_price ?: 0, 4);
            $varianceValue = bcmul(ltrim($variance, '-'), $cost, 4);

            $opnameItem->update([
                'physical_qty_whole' => $whole,
                'physical_qty_loose' => $loose,
                'physical_qty_base' => $physicalBase,
                'counted_qty' => $physicalBase,
                'variance_qty' => $variance,
                'variance' => $variance,
                'variance_value' => $varianceValue,
                'is_counted' => true,
            ]);

            return $opnameItem->refresh()->load(['item.inventoryUnit', 'item.baseUnit', 'department']);
        });
    }

    /**
     * Ambil ulang system_qty dari stock_balances TERKINI untuk 1 baris Opname.
     *
     * system_qty/system_qty_base sengaja dibekukan sekali saat sesi dibuat
     * (lihat startSession()) supaya perbandingan fisik-vs-sistem tidak
     * bergeser sendiri selagi tim masih menghitung. Tapi kalau ada koreksi
     * data stok (mis. Open Stock di-void lalu di-post ulang dengan angka
     * benar) SETELAH sesi Opname sudah terlanjur dibuat, baseline yang
     * dibekukan itu jadi basi dan tidak akan pernah ikut update sendiri.
     * Method ini jadi jalan keluarnya: sinkronkan ulang manual per item,
     * tanpa perlu membatalkan/mengulang seluruh sesi.
     */
    public function syncItemBaseline(OpnameItem $opnameItem): OpnameItem
    {
        return DB::transaction(function () use ($opnameItem): OpnameItem {
            $opnameItem = OpnameItem::query()
                ->with(['session', 'item'])
                ->lockForUpdate()
                ->findOrFail($opnameItem->id);

            if ($opnameItem->session->status !== OpnameSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Sinkronisasi hanya bisa dilakukan saat sesi masih draft.',
                ]);
            }

            $systemQty = $this->systemQty(
                (int) $opnameItem->session->tenant_id,
                (int) $opnameItem->session->outlet_id,
                (int) $opnameItem->item_id
            );

            $variance = bcsub($systemQty, (string) $opnameItem->physical_qty_base, 6);
            $cost = Decimal::toFixed($opnameItem->item?->standard_cost ?: $opnameItem->item?->last_purchase_price ?: 0, 4);
            $varianceValue = bcmul(ltrim($variance, '-'), $cost, 4);

            $opnameItem->update([
                'system_qty' => $systemQty,
                'system_qty_base' => $systemQty,
                'variance_qty' => $variance,
                'variance' => $variance,
                'variance_value' => $varianceValue,
            ]);

            return $opnameItem->refresh()->load(['item.inventoryUnit', 'item.baseUnit', 'department']);
        });
    }

    public function submit(OpnameSession $session, int $userId): OpnameSession
    {
        $session = DB::transaction(function () use ($session, $userId): OpnameSession {
            $session = OpnameSession::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== OpnameSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya sesi draft yang bisa disubmit.',
                ]);
            }

            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada item untuk opname.',
                ]);
            }

            if ($session->items->contains(fn (OpnameItem $item): bool => ! $item->is_counted)) {
                throw ValidationException::withMessages([
                    'items' => 'Semua item wajib diisi sebelum submit.',
                ]);
            }

            $session->update([
                'status' => OpnameSession::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            return $session->refresh();
        });

        $approvers = $this->recipients->usersForApproval((int) $session->tenant_id, 'approve_opname', $session->department_id, $session->outlet_id);
        Notification::send($approvers, new WorkflowNotification(
            "Opname {$session->outlet?->name} perlu persetujuan",
            "Opname {$session->type} outlet {$session->outlet?->name} tanggal {$session->opname_date?->format('d M Y')} diajukan dan menunggu persetujuan Anda.",
            route('operations.opname.show', $session),
            'operations',
        ));

        return $session;
    }

    public function approve(OpnameSession $session, int $userId): OpnameSession
    {
        $session = DB::transaction(function () use ($session, $userId): OpnameSession {
            $session = OpnameSession::query()
                ->with(['items.item'])
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== OpnameSession::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya sesi submitted yang bisa di-approve.',
                ]);
            }

            // Agregasi per item_id agar item shared (multi-dept) hanya menghasilkan satu mutasi
            $itemGroups = $session->items->groupBy('item_id');

            foreach ($itemGroups as $itemId => $rows) {
                // Skip jika sudah ada mutasi di salah satu baris (idempoten)
                if ($rows->contains(fn (OpnameItem $r): bool => $r->mutation_id !== null)) {
                    continue;
                }

                // Total fisik dari semua departemen untuk item ini
                $totalPhysicalBase = $rows->reduce(
                    fn (string $carry, OpnameItem $r): string => bcadd($carry, (string) $r->physical_qty_base, 6),
                    '0.000000'
                );

                // system_qty_base sama di semua baris untuk item yang sama
                $systemQtyBase = (string) $rows->first()->system_qty_base;

                // Variance agregat: sistem - total fisik semua departemen
                $aggregateVariance = bcsub($systemQtyBase, $totalPhysicalBase, 6);

                if (bccomp($aggregateVariance, '0.000000', 6) === 0) {
                    continue;
                }

                $firstRow = $rows->first();

                $mutation = $this->stockLedgerService->opnameAdjustment([
                    'tenant_id'      => $session->tenant_id,
                    'outlet_id'      => $session->outlet_id,
                    'item_id'        => $firstRow->item_id,
                    'unit_id'        => $firstRow->item?->base_unit_id ?: $firstRow->unit_id,
                    'stock_target'   => StockMutation::TARGET_OUTLET_DAILY,
                    'opname_type'    => $session->type,
                    'qty_change'     => bcmul('-1', $aggregateVariance, 6),
                    'reference_type' => OpnameItem::class,
                    'reference_id'   => $firstRow->id,
                    'performed_by'   => $userId,
                    'performed_at'   => now(),
                    'notes'          => "Opname {$session->type} {$session->opname_date?->format('Y-m-d')}",
                    'metadata'       => [
                        'opname_session_id'  => $session->id,
                        'opname_item_ids'    => $rows->pluck('id')->all(),
                    ],
                ]);

                // Tautkan mutasi ke SEMUA baris departemen untuk item ini
                $rows->each(fn (OpnameItem $r): bool => (bool) $r->update(['mutation_id' => $mutation->id]));
            }

            $session->update([
                'status' => OpnameSession::STATUS_PROCESSED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            return $session->refresh()->load(['items.item', 'items.mutation']);
        });

        $session->createdBy?->notify(new WorkflowNotification(
            "Opname {$session->outlet?->name} disetujui",
            "Opname {$session->type} outlet {$session->outlet?->name} tanggal {$session->opname_date?->format('d M Y')} sudah disetujui dan diposting.",
            route('operations.opname.show', $session),
            'operations',
        ));

        return $session;
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array{processed: int, failed: int, errors: list<string>}
     */
    public function bulkSubmit(array $sessionIds, int $userId): array
    {
        $processed = 0;
        $errors = [];

        $sessions = OpnameSession::query()->with('outlet')->whereIn('id', $sessionIds)->get();

        foreach ($sessions as $session) {
            $label = $session->outlet?->name.' - '.optional($session->opname_date)->format('d/m/Y');

            try {
                $this->submit($session, $userId);
                $processed++;
            } catch (ValidationException $exception) {
                $errors[] = "{$label}: ".collect($exception->errors())->flatten()->implode(' ');
            } catch (\Throwable $throwable) {
                $errors[] = "{$label}: {$throwable->getMessage()}";
            }
        }

        return [
            'processed' => $processed,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array{processed: int, failed: int, errors: list<string>}
     */
    public function bulkApprove(array $sessionIds, int $userId): array
    {
        $processed = 0;
        $errors = [];

        $sessions = OpnameSession::query()->with('outlet')->whereIn('id', $sessionIds)->get();

        foreach ($sessions as $session) {
            $label = $session->outlet?->name.' - '.optional($session->opname_date)->format('d/m/Y');

            try {
                $this->approve($session, $userId);
                $processed++;
            } catch (ValidationException $exception) {
                $errors[] = "{$label}: ".collect($exception->errors())->flatten()->implode(' ');
            } catch (\Throwable $throwable) {
                $errors[] = "{$label}: {$throwable->getMessage()}";
            }
        }

        return [
            'processed' => $processed,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    public function countDailyItems(int $tenantId, int $outletId, ?int $departmentId = null): int
    {
        return $this->itemsForOpname($tenantId, $outletId, OpnameSession::TYPE_DAILY, $departmentId)->count();
    }

    private function assertOutletBelongsToTenant(int $outletId, int $tenantId): void
    {
        $exists = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $outletId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet tidak valid untuk tenant ini.',
            ]);
        }
    }

    /**
     * @return Collection<int, Item>
     */
    private function itemsForOpname(int $tenantId, int $outletId, string $type, ?int $departmentId = null): Collection
    {
        $frequency = $type === OpnameSession::TYPE_MONTHLY ? 'MONTHLY' : 'DAILY';

        $itemIds = DB::table('item_outlets')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where(function ($query): void {
                $query->where('status', 'ACTIVE')->orWhere('is_active', true);
            })
            ->where(function ($query) use ($frequency): void {
                $query->where('opname_frequency', $frequency)->orWhereNull('opname_frequency');
            })
            ->pluck('item_id');

        $query = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with(['baseUnit', 'inventoryUnit', 'primaryDepartment', 'departments'])
            ->orderBy('name');

        if ($itemIds->isNotEmpty()) {
            $query->whereIn('id', $itemIds->all());
        }

        // Sesi opname yang di-scope ke satu departemen cuma boleh memuat item
        // milik departemen tsb (primary atau ikut relasi departments many-to-many)
        // -- ini akar perbaikan supaya BAR tidak lagi melihat/menghitung item
        // milik KITCHEN, begitu juga sebaliknya.
        if ($departmentId) {
            $query->where(function ($q) use ($departmentId): void {
                $q->where('primary_department_id', $departmentId)
                    ->orWhereHas('departments', fn ($dq) => $dq->where('departments.id', $departmentId));
            });
        }

        return $query->get();
    }

    private function systemQty(int $tenantId, int $outletId, int $itemId): string
    {
        $qty = DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('item_id', $itemId)
            ->sum('qty_on_hand');

        return Decimal::toFixed((string) ($qty ?? 0), 6);
    }

    private function physicalBaseQty(?Item $item, string $whole, string $loose): string
    {
        if (! $item) {
            return bcadd($whole, $loose, 6);
        }

        $ratio = $item->inventory_ratio ? Decimal::toFixed($item->inventory_ratio, 6) : '1.000000';

        return bcadd(bcmul($whole, $ratio, 6), $loose, 6);
    }
}
