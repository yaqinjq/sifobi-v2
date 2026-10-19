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
<tr x-show="isVisible()"
    x-data="inventoryCountLine({
       lineId: {{ $line->id }},
       url: @js(route('operations.inventory-count.lines.autosave', $line)),
       zeroUrl: @js(route('operations.inventory-count.lines.zero', $line)),
       itemName: @js($line->item?->name ?? ''),
       location: @js($line->location),
       category: @js($line->subcategory_label ?: 'Lainnya'),
       qtyWhole: @js($line->qty_whole !== null ? (string) $line->qty_whole : ''),
       qtyLoose: @js($line->qty_loose !== null ? (string) $line->qty_loose : ''),
       requiresConversion: @js($line->requires_conversion),
       configValid: @js($line->config_valid),
       isCounted: @js($line->is_counted),
    })"
    class="border-t border-gray-100">
    <td class="sticky left-0 z-10 bg-white px-2 py-2 text-center text-gray-400 w-10">{{ $rowNum ?? '' }}</td>
    <td class="sticky left-10 z-10 bg-white px-3 py-2 align-top">
        <p class="font-medium text-gray-900">{{ $line->item?->name ?? '-' }}</p>
        @if(! $line->config_valid)
            <span class="inline-flex items-center gap-1 text-[11px] text-amber-700" title="Konversi Utuh/Ecer belum dikonfigurasi -- tidak bisa dikirim sampai diperbaiki Super Admin.">
                <i class="ti ti-alert-triangle" aria-hidden="true"></i> Satuan belum valid
            </span>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $locationLabel ?: '-' }}</td>
    <td class="px-2 py-2">
        <input type="text"
               inputmode="decimal"
               x-model="qtyWhole"
               @input.debounce.600ms="save()"
               placeholder="0"
               class="sf-input text-center min-h-11 text-base"
               {{ $document->status !== 'DRAFT' ? 'disabled' : '' }}>
        <p class="text-center text-[11px] text-gray-400 mt-0.5">{{ $wholeUnit }}</p>
    </td>
    <td class="px-2 py-2">
        @if($line->requires_conversion)
            <input type="text"
                   inputmode="decimal"
                   x-model="qtyLoose"
                   @input.debounce.600ms="save()"
                   placeholder="0"
                   class="sf-input text-center min-h-11 text-base"
                   {{ $document->status !== 'DRAFT' ? 'disabled' : '' }}>
            <p class="text-center text-[11px] text-gray-400 mt-0.5">{{ $looseUnit }}</p>
        @else
            <p class="text-center text-gray-300">-</p>
        @endif
    </td>
    <td class="px-3 py-2 text-center whitespace-nowrap">
        <span x-show="isCounted" class="inline-flex items-center gap-1 text-xs font-semibold text-green-600"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
        <span x-show="!isCounted" class="inline-flex items-center gap-1 text-xs text-gray-400"><i class="ti ti-circle-dashed" aria-hidden="true"></i></span>
        <span x-show="saving" class="block text-[11px] text-gray-400">...</span>
        <span x-show="saved" x-transition class="block text-[11px] font-semibold text-primary-700">Tersimpan</span>
        <span x-show="saveFailed" x-cloak class="block text-[11px] font-semibold text-red-600">Gagal</span>
        <span x-show="conflict" x-cloak class="block text-[11px] font-semibold text-red-600">Bentrok</span>
        @if($document->status === 'DRAFT')
            <button type="button" @click="markZero()" class="block w-full mt-1 text-[11px] text-gray-500 underline">Nol</button>
        @endif
    </td>
</tr>
