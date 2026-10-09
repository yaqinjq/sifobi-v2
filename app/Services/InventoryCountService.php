<?php

namespace App\Services;

use App\Exceptions\InventoryCountConflictException;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountDocumentRevision;
use App\Modules\Operations\Models\InventoryCountLine;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use App\Support\Decimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Inventory Simple -- murni pendataan/observasi fisik. TIDAK PERNAH
 * memanggil StockLedgerService, OpnameService, SpoilWasteService,
 * OpenStockService, atau GoodsReceiptService, dan TIDAK PERNAH menulis ke
 * stock_balances/stock_mutations. Status DRAFT -> SUBMITTED cuma berarti
 * "hasil hitung sudah dikirim", bukan approval/posting stok apa pun.
 */
class InventoryCountService
{
    /**
     * @return Collection<int, InventoryCountTemplate>
     */
    public function availableTemplates(int $tenantId, int $departmentId): Collection
    {
        return InventoryCountTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InventoryCountTemplate::STATUS_ACTIVE)
            ->where(fn ($q) => $q->where('department_id', $departmentId)->orWhere('is_generic', true))
            ->orderByDesc('is_generic') // template spesifik departemen duluan, generik di akhir.
            ->orderBy('name')
            ->get();
    }

    /**
     * Buat dokumen baru untuk scope+tanggal+template ini, ATAU kembalikan
     * yang sudah ada (idempotent -- aman dipanggil berulang akibat
     * double-klik/double-submit).
     */
    public function createOrResumeDraft(
        int $tenantId,
        int $outletId,
        int $departmentId,
        InventoryCountTemplate $template,
        Carbon $businessDate,
        int $userId
    ): InventoryCountDocument {
        $existing = InventoryCountDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('department_id', $departmentId)
            ->where('template_id', $template->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($tenantId, $outletId, $departmentId, $template, $businessDate, $userId): InventoryCountDocument {
                $document = InventoryCountDocument::query()->create([
                    'tenant_id' => $tenantId,
                    'outlet_id' => $outletId,
                    'department_id' => $departmentId,
                    'template_id' => $template->id,
                    'business_date' => $businessDate->toDateString(),
                    'status' => InventoryCountDocument::STATUS_DRAFT,
                    'version' => 1,
                    'created_by' => $userId,
                ]);

                $this->materializeLines($document, $template, $tenantId);

                return $document;
            });
        } catch (QueryException $exception) {
            // Race condition: 2 request bersamaan lolos cek "existing" di
            // atas lalu sama-sama coba create -- unique constraint di DB
            // yang jadi penjaga terakhir. Yang kalah cukup fetch ulang.
            $existing = InventoryCountDocument::query()
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('department_id', $departmentId)
                ->where('template_id', $template->id)
                ->whereDate('business_date', $businessDate->toDateString())
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function materializeLines(InventoryCountDocument $document, InventoryCountTemplate $template, int $tenantId): void
    {
        $templateItems = InventoryCountTemplateItem::query()
            ->where('tenant_id', $tenantId)
            ->where('template_id', $template->id)
            ->where('is_active', true)
            ->with('item')
            ->orderBy('sort_order')
            ->get();

        foreach ($templateItems as $templateItem) {
            $item = $templateItem->item;

            if (! $item) {
                continue;
            }

            $locations = $templateItem->locations ?: [InventoryCountLine::LOCATION_GENERIC];

            foreach ($locations as $location) {
                $config = $this->resolveLineConfig($templateItem, $item);

                InventoryCountLine::query()->create([
                    'tenant_id' => $tenantId,
                    'document_id' => $document->id,
                    'item_id' => $item->id,
                    'subcategory_label' => $templateItem->subcategory_label,
                    'sort_order' => $templateItem->sort_order,
                    'location' => $location,
                    'whole_unit_id' => $config['whole_unit_id'],
                    'loose_unit_id' => $config['loose_unit_id'],
                    'conversion_factor' => $config['conversion_factor'],
                    'requires_conversion' => $config['requires_conversion'],
                    'config_valid' => $config['config_valid'],
                    'is_counted' => false,
                ]);
            }
        }
    }

    /**
     * @return array{requires_conversion: bool, config_valid: bool, whole_unit_id: ?int, loose_unit_id: ?int, conversion_factor: ?string}
     */
    private function resolveLineConfig(InventoryCountTemplateItem $templateItem, Item $item): array
    {
        if (! $templateItem->requires_conversion) {
            return [
                'requires_conversion' => false,
                'config_valid' => true,
                'whole_unit_id' => $item->base_unit_id ?: $item->inventory_unit_id,
                'loose_unit_id' => null,
                'conversion_factor' => null,
            ];
        }

        $hasValidRatio = $item->inventory_unit_id
            && $item->base_unit_id
            && (int) $item->inventory_unit_id !== (int) $item->base_unit_id
            && $item->inventory_ratio
            && bccomp((string) $item->inventory_ratio, '0', 6) > 0;

        if ($hasValidRatio) {
            return [
                'requires_conversion' => true,
                'config_valid' => true,
                'whole_unit_id' => $item->inventory_unit_id,
                'loose_unit_id' => $item->base_unit_id,
                'conversion_factor' => (string) $item->inventory_ratio,
            ];
        }

        // Form minta Utuh+Ecer untuk item ini, TAPI item master belum punya
        // inventory_unit/ratio yang valid -- blokir "siap dikirim" (lihat
        // submit()), TAPI baris tetap dibuat supaya staf masih bisa mengisi
        // angka mentah sebagai draft dengan peringatan.
        return [
            'requires_conversion' => true,
            'config_valid' => false,
            'whole_unit_id' => $item->inventory_unit_id ?: $item->base_unit_id,
            'loose_unit_id' => $item->base_unit_id,
            'conversion_factor' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function autosaveLine(InventoryCountLine $line, array $data, int $userId, int $expectedDocVersion): InventoryCountLine
    {
        return DB::transaction(function () use ($line, $data, $userId, $expectedDocVersion): InventoryCountLine {
            $document = InventoryCountDocument::query()->lockForUpdate()->findOrFail($line->document_id);

            if ($document->status !== InventoryCountDocument::STATUS_DRAFT) {
                throw new InventoryCountConflictException('Dokumen sudah terkirim, tidak bisa diedit langsung. Ajukan revisi dulu.');
            }

            if ((int) $document->version !== $expectedDocVersion) {
                throw new InventoryCountConflictException('Dokumen ini sudah diperbarui dari perangkat/tab lain. Muat ulang sebelum menyimpan lagi.');
            }

            $wholeProvided = array_key_exists('qty_whole', $data) && $data['qty_whole'] !== null && $data['qty_whole'] !== '';
            $looseProvided = array_key_exists('qty_loose', $data) && $data['qty_loose'] !== null && $data['qty_loose'] !== '';

            // Decimal::toFixed() menormalkan koma -> titik sebelum disimpan
            // -- kalau cuma assign $data['qty_whole'] mentah, string dengan
            // koma ("12,5") akan tersimpan apa adanya ke kolom decimal dan
            // bisa dibaca salah (atau ditolak diam-diam) tergantung driver
            // DB, bukan langsung error yang jelas.
            $wholeNormalized = $wholeProvided ? Decimal::toFixed($data['qty_whole'], 6) : null;
            $looseNormalized = $looseProvided ? Decimal::toFixed($data['qty_loose'], 6) : null;

            $isCounted = $line->requires_conversion
                ? ($wholeProvided || $looseProvided)
                : $wholeProvided;

            $line->qty_whole = $wholeProvided ? $wholeNormalized : ($line->requires_conversion ? $line->qty_whole : null);
            $line->qty_loose = $line->requires_conversion
                ? ($looseProvided ? $looseNormalized : $line->qty_loose)
                : null;

            // Autosave field kosong TIDAK PERNAH menjadikan is_counted true
            // -- kalau request ini tidak membawa nilai apa pun, biarkan
            // status is_counted seperti sebelumnya (mis. field lain di baris
            // yang sama sudah terisi duluan), bukan dipaksa false hanya
            // karena request kali ini kosong untuk field yang ditanya.
            if ($wholeProvided || $looseProvided) {
                $line->is_counted = true;
                $line->counted_at = now();
                $line->counted_by = $userId;
            } elseif (array_key_exists('clear', $data) && $data['clear'] === true) {
                // Aksi eksplisit "kosongkan" (bukan autosave kosong pasif).
                $line->qty_whole = null;
                $line->qty_loose = null;
                $line->is_counted = false;
                $line->counted_at = null;
                $line->counted_by = null;
            }

            if (array_key_exists('notes', $data)) {
                $line->notes = $data['notes'] ?: null;
            }

            $line->save();

            $document->version += 1;
            $document->last_saved_by = $userId;
            $document->last_saved_at = now();
            $document->save();

            return $line->refresh();
        });
    }

    /**
     * Tombol "Stok Nol" -- isi 0 secara eksplisit (beda dari field kosong
     * yang berarti "belum dihitung").
     */
    public function markZero(InventoryCountLine $line, int $userId, int $expectedDocVersion): InventoryCountLine
    {
        return $this->autosaveLine($line, [
            'qty_whole' => '0',
            'qty_loose' => $line->requires_conversion ? '0' : null,
        ], $userId, $expectedDocVersion);
    }

    public function submit(InventoryCountDocument $document, int $userId, int $expectedVersion): InventoryCountDocument
    {
        return DB::transaction(function () use ($document, $userId, $expectedVersion): InventoryCountDocument {
            $document = InventoryCountDocument::query()->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== InventoryCountDocument::STATUS_DRAFT) {
                throw new InventoryCountConflictException('Dokumen ini bukan draft, tidak bisa dikirim ulang.');
            }

            if ((int) $document->version !== $expectedVersion) {
                throw new InventoryCountConflictException('Dokumen ini sudah diperbarui dari perangkat/tab lain. Muat ulang sebelum mengirim.');
            }

            $lines = InventoryCountLine::query()->where('document_id', $document->id)->with('item')->get();

            $uncounted = $lines->where('is_counted', false);
            $unconfigured = $lines->where('requires_conversion', true)->where('config_valid', false);

            if ($uncounted->isNotEmpty() || $unconfigured->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => $this->blockedSubmitMessages($uncounted, $unconfigured),
                ]);
            }

            $document->update([
                'status' => InventoryCountDocument::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
                'version' => $document->version + 1,
            ]);

            return $document->refresh();
        });
    }

    /**
     * @param  Collection<int, InventoryCountLine>  $uncounted
     * @param  Collection<int, InventoryCountLine>  $unconfigured
     * @return list<string>
     */
    private function blockedSubmitMessages(Collection $uncounted, Collection $unconfigured): array
    {
        $messages = [];

        foreach ($uncounted as $line) {
            $messages[] = "{$line->item?->name} ({$line->location}): belum dihitung.";
        }

        foreach ($unconfigured as $line) {
            $messages[] = "{$line->item?->name} ({$line->location}): butuh konversi Utuh/Ecer tapi satuan item belum dikonfigurasi, hubungi Super Admin.";
        }

        return $messages;
    }

    /**
     * Koreksi berizin: balik status SUBMITTED -> DRAFT dengan alasan
     * tercatat, BUKAN overwrite diam-diam. Pengecekan permission "siapa
     * boleh minta revisi" dilakukan di controller (reuse approve_opname),
     * bukan di sini.
     */
    public function requestRevision(InventoryCountDocument $document, int $userId, string $reason): InventoryCountDocument
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan revisi wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($document, $userId, $reason): InventoryCountDocument {
            $document = InventoryCountDocument::query()->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== InventoryCountDocument::STATUS_SUBMITTED) {
                throw new InventoryCountConflictException('Hanya dokumen SUBMITTED yang bisa direvisi.');
            }

            InventoryCountDocumentRevision::query()->create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'from_status' => InventoryCountDocument::STATUS_SUBMITTED,
                'to_status' => InventoryCountDocument::STATUS_DRAFT,
                'reason' => $reason,
                'performed_by' => $userId,
                'performed_at' => now(),
            ]);

            $document->update([
                'status' => InventoryCountDocument::STATUS_DRAFT,
                'version' => $document->version + 1,
            ]);

            return $document->refresh();
        });
    }
}
