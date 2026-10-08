@php
    $locationLabels = [
        'GUDANG' => 'Gudang',
        'BAR_AREA' => 'Bar Area',
        'KITCHEN_AREA' => 'Kitchen Area',
        'GENERIC' => '',
    ];
    $locationLabel = $locationLabels[$line->location] ?? $line->location;
    $wholeUnit = $line->wholeUnit?->abbreviation ?? $line->wholeUnit?->code ?? '';
    $looseUnit = $line->looseUnit?->abbreviation ?? $line->looseUnit?->code ?? '';
@endphp
<div class="sf-card p-4"
     x-data="inventoryCountLine({
        lineId: {{ $line->id }},
        url: @js(route('operations.inventory-count.lines.autosave', $line)),
        qtyWhole: @js($line->qty_whole !== null ? (string) $line->qty_whole : ''),
        qtyLoose: @js($line->qty_loose !== null ? (string) $line->qty_loose : ''),
        requiresConversion: @js($line->requires_conversion),
        configValid: @js($line->config_valid),
        isCounted: @js($line->is_counted),
     })">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900">{{ $line->item?->name ?? '-' }}</p>
            @if($locationLabel)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 mt-1">{{ $locationLabel }}</span>
            @endif
        </div>
        <div class="shrink-0">
            <span x-show="isCounted" class="inline-flex items-center gap-1 text-xs font-semibold text-green-600"><i class="ti ti-circle-check" aria-hidden="true"></i> Terhitung</span>
            <span x-show="!isCounted" class="inline-flex items-center gap-1 text-xs text-gray-400"><i class="ti ti-circle-dashed" aria-hidden="true"></i> Belum</span>
        </div>
    </div>

    @if(! $line->config_valid)
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            Konversi Utuh/Ecer item ini belum dikonfigurasi. Angka tetap bisa disimpan sebagai draft, tapi dokumen tidak bisa dikirim sampai ini diperbaiki Super Admin.
        </div>
    @endif

    <div class="mt-3 grid grid-cols-1 {{ $line->requires_conversion ? 'sm:grid-cols-2' : '' }} gap-3">
        <div>
            <label class="sf-label">{{ $line->requires_conversion ? "Utuh ({$wholeUnit})" : "Qty ({$wholeUnit})" }}</label>
            <input type="text"
                   inputmode="decimal"
                   x-model="qtyWhole"
                   @input.debounce.600ms="save()"
                   placeholder="0"
                   class="sf-input text-base min-h-11"
                   {{ $document->status !== 'DRAFT' ? 'disabled' : '' }}>
        </div>
        @if($line->requires_conversion)
            <div>
                <label class="sf-label">Ecer ({{ $looseUnit }})</label>
                <input type="text"
                       inputmode="decimal"
                       x-model="qtyLoose"
                       @input.debounce.600ms="save()"
                       placeholder="0"
                       class="sf-input text-base min-h-11"
                       {{ $document->status !== 'DRAFT' ? 'disabled' : '' }}>
            </div>
        @endif
    </div>

    @if($document->status === 'DRAFT')
        <div class="mt-3 flex items-center justify-between gap-2">
            <button type="button" @click="markZero()" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9">
                Stok Nol
            </button>
            <span x-show="saving" class="text-xs text-gray-400">Menyimpan...</span>
            <span x-show="saved" x-transition class="text-xs font-semibold text-primary-700">Tersimpan</span>
        </div>
    @endif

    <div x-show="conflict" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 p-2.5 text-xs text-red-700">
        Dokumen ini sudah diperbarui dari perangkat/tab lain. Muat ulang halaman sebelum menyimpan lagi.
    </div>
</div>
