<?php

namespace App\Services;

use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderApprovalEvent;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseOrderService
{
    public function __construct(private readonly WiproIntegrationService $wipro = new WiproIntegrationService()) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId): PurchaseOrder {
            $tenantId = (int) $data['tenant_id'];

            $poNumber = $this->generatePoNumber($tenantId, $data['po_type']);

            $po = PurchaseOrder::query()->create([
                'tenant_id'    => $tenantId,
                'outlet_id'    => $data['outlet_id'],
                'department_id' => $data['department_id'] ?? null,
                'po_number'    => $poNumber,
                'po_type'      => $data['po_type'],
                'needed_at'    => $data['needed_at'] ?? null,
                'status'       => PurchaseOrder::STATUS_DRAFT,
                'notes'        => $data['notes'] ?? null,
                'requested_by' => $userId,
            ]);

            return $po;
        });
    }

    public function addItem(PurchaseOrder $po, int $tenantId, array $data): PurchaseOrderItem
    {
        if (! $po->canEdit()) {
            throw ValidationException::withMessages(['status' => 'Item hanya bisa ditambah saat PO masih draft.']);
        }

        return PurchaseOrderItem::query()->create([
            'tenant_id'         => $tenantId,
            'purchase_order_id' => $po->id,
            'item_id'           => $data['item_id'],
            'unit_id'           => $data['unit_id'],
            'qty_ordered'       => $data['qty_ordered'],
            'unit_cost'         => $data['unit_cost'] ?? null,
            'notes'             => $data['notes'] ?? null,
        ]);
    }

    public function removeItem(PurchaseOrder $po, PurchaseOrderItem $item): void
    {
        if (! $po->canEdit()) {
            throw ValidationException::withMessages(['status' => 'Item hanya bisa dihapus saat PO masih draft.']);
        }

        $item->delete();
    }

    public function submit(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->canSubmit()) {
                throw ValidationException::withMessages(['status' => 'Hanya PO draft yang bisa diajukan.']);
            }

            if (! $po->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'PO harus memiliki minimal 1 item sebelum diajukan.']);
            }

            $oldStatus = $po->status;

            $po->update([
                'status'       => PurchaseOrder::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $this->logEvent($po, $userId, PurchaseOrderApprovalEvent::EVENT_SUBMIT, $oldStatus, PurchaseOrder::STATUS_SUBMITTED);

            return $po->refresh();
        });
    }

    public function approve(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->canApprove()) {
                throw ValidationException::withMessages(['status' => 'Hanya PO yang diajukan yang bisa disetujui.']);
            }

            $oldStatus = $po->status;

            $po->update([
                'status'      => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $this->logEvent($po, $userId, PurchaseOrderApprovalEvent::EVENT_APPROVE, $oldStatus, PurchaseOrder::STATUS_APPROVED);

            return $po->refresh();
        });
    }

    public function reject(PurchaseOrder $po, int $userId, string $notes): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId, $notes): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->canReject()) {
                throw ValidationException::withMessages(['status' => 'PO ini tidak bisa ditolak pada status saat ini.']);
            }

            $oldStatus = $po->status;

            $po->update([
                'status'           => PurchaseOrder::STATUS_REJECTED,
                'rejected_by'      => $userId,
                'rejected_at'      => now(),
                'rejection_notes'  => $notes,
            ]);

            $this->logEvent($po, $userId, PurchaseOrderApprovalEvent::EVENT_REJECT, $oldStatus, PurchaseOrder::STATUS_REJECTED, $notes);

            return $po->refresh();
        });
    }

    public function send(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        $po = DB::transaction(function () use ($po, $userId): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->canSend()) {
                throw ValidationException::withMessages(['status' => 'Hanya PO yang disetujui yang bisa dikirim.']);
            }

            $oldStatus = $po->status;

            $po->update([
                'status'  => PurchaseOrder::STATUS_SENT,
                'sent_by' => $userId,
                'sent_at' => now(),
            ]);

            $this->logEvent($po, $userId, PurchaseOrderApprovalEvent::EVENT_SEND, $oldStatus, PurchaseOrder::STATUS_SENT);

            return $po->refresh();
        });

        if ($po->po_type === PurchaseOrder::TYPE_CENTRAL_KITCHEN) {
            $this->syncToWipro($po);
        }

        return $po->refresh();
    }

    /**
     * Manually retry pushing a SENT Central Kitchen PO to Wipro after a prior sync failure.
     */
    public function resend(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_SENT || $po->po_type !== PurchaseOrder::TYPE_CENTRAL_KITCHEN) {
            throw ValidationException::withMessages(['status' => 'Hanya PO Central Kitchen berstatus terkirim yang bisa dikirim ulang ke Wipro.']);
        }

        $this->syncToWipro($po);

        return $po->refresh();
    }

    /**
     * Push a SENT Central Kitchen PO to Wipro and record the outcome on the PO row.
     * Sync failures never block or reverse the SENT status — the outlet PIC already
     * approved the order; a transport failure must stay visible and retryable, not silent.
     */
    private function syncToWipro(PurchaseOrder $po): void
    {
        try {
            $result = $this->wipro->pushOrder($po);

            $po->forceFill([
                'external_reference' => $result['wipro_order_number'] ?? $result['wipro_order_id'],
                'external_synced_at' => now(),
                'external_sync_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $po->forceFill([
                'external_sync_error' => $exception->getMessage(),
            ])->save();
        }
    }

    public function close(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->canClose()) {
                throw ValidationException::withMessages(['status' => 'Hanya PO terkirim yang bisa diselesaikan.']);
            }

            $oldStatus = $po->status;

            $po->update([
                'status'    => PurchaseOrder::STATUS_CLOSED,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            $this->logEvent($po, $userId, PurchaseOrderApprovalEvent::EVENT_CLOSE, $oldStatus, PurchaseOrder::STATUS_CLOSED);

            return $po->refresh();
        });
    }

    public function generatePoNumber(int $tenantId, string $poType): string
    {
        $typeAbbr = match ($poType) {
            PurchaseOrder::TYPE_OCIA_ROASTERY   => 'OC',
            PurchaseOrder::TYPE_CENTRAL_KITCHEN  => 'CK',
            PurchaseOrder::TYPE_DRYGOOD          => 'DG',
            default                              => 'PO',
        };

        $prefix = "PO-{$typeAbbr}-".now()->format('ym').'-';

        $latest = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('po_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('po_number')
            ->value('po_number');

        $next = $latest ? ((int) substr((string) $latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function logEvent(
        PurchaseOrder $po,
        int $userId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        ?string $notes = null
    ): void {
        PurchaseOrderApprovalEvent::query()->create([
            'tenant_id'         => $po->tenant_id,
            'purchase_order_id' => $po->id,
            'actor_id'          => $userId,
            'event_type'        => $eventType,
            'from_status'       => $fromStatus,
            'to_status'         => $toStatus,
            'notes'             => $notes,
        ]);
    }
}
