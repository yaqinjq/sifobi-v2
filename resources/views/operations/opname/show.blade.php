@extends('layouts.app')

@section('title', 'Detail Opname')

@section('content')
@php
    $counted = $session->items->where('is_counted', true)->count();
    $total = $session->items->count();
@endphp

<x-sf.page-header title="Opname {{ optional($session->opname_date)->format('d M Y') }}" subtitle="{{ $session->outlet?->name ?? '-' }}" back="{{ route('operations.opname.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4"
     x-data="{ counted: {{ $counted }}, total: {{ $total }} }"
     @item-counted="counted = $event.detail.counted">
    <x-sf.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="{{ $session->status_badge_class }}">{{ $session->status }}</span>
                <p class="text-sm text-gray-500 mt-2">Shift: {{ $session->shift ?: '-' }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Progress</p>
                <p class="text-lg font-bold text-gray-900"><span x-text="counted"></span> / <span x-text="total"></span> item</p>
            </div>
        </div>
        <div class="mt-4 h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-primary-700 transition-all" :style="`width: ${total === 0 ? 0 : (counted / total) * 100}%`"></div>
        </div>
    </x-sf.card>

    <div class="space-y-3">
        @foreach($session->items as $opnameItem)
            @php
                $item = $opnameItem->item;
                $inventoryUnit = $item?->inventoryUnit?->abbreviation ?? $opnameItem->unit?->abbreviation ?? 'unit';
                $baseUnit = $item?->baseUnit?->abbreviation ?? 'base';
            @endphp
            <div class="sf-card p-4"
                 x-data="opnameItemCard({
                    url: @js(route('operations.opname.update-item', [$session, $opnameItem])),
                    variance: @js((string) $opnameItem->variance),
                    varianceValue: @js((string) $opnameItem->variance_value),
                    physicalBase: @js((string) $opnameItem->physical_qty_base),
                    qtyWhole: @js((string) $opnameItem->physical_qty_whole),
                    qtyLoose: @js((string) $opnameItem->physical_qty_loose),
                    wasCounted: @js((bool) $opnameItem->is_counted)
                 })">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $item?->name ?? '-' }}</p>
                        <p class="text-xs text-gray-500">{{ $item?->canonical_sku ?? '-' }}</p>
                    </div>
                    <span class="badge-draft">{{ $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-' }}</span>
                </div>

                <div class="mt-4 rounded-xl bg-gray-50 px-3 py-2 text-sm flex justify-between gap-3">
                    <span class="text-gray-500">Sistem</span>
                    <span class="font-semibold text-gray-900">{{ $opnameItem->system_qty_base }} {{ $baseUnit }}</span>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="sf-label">Fisik utuh ({{ $inventoryUnit }})</label>
                        <input type="text"
                               inputmode="decimal"
                               x-model="qtyWhole"
                               @input.debounce.500ms="save()"
                               value="{{ $opnameItem->physical_qty_whole }}"
                               class="sf-input text-base min-h-11"
                               @disabled($session->status !== 'DRAFT')>
                    </div>
                    <div>
                        <label class="sf-label">Fisik ecer ({{ $baseUnit }})</label>
                        <input type="text"
                               inputmode="decimal"
                               x-model="qtyLoose"
                               @input.debounce.500ms="save()"
                               value="{{ $opnameItem->physical_qty_loose }}"
                               class="sf-input text-base min-h-11"
                               @disabled($session->status !== 'DRAFT')>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
                        <span class="text-gray-500">Fisik base</span>
                        <span class="font-semibold text-gray-900"><span x-text="physicalBase"></span> {{ $baseUnit }}</span>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
                        <span class="text-gray-500">Selisih</span>
                        <span>
                            <span x-show="Number(variance) < 0" class="font-semibold text-red-600" x-text="`${variance} {{ $baseUnit }}`"></span>
                            <span x-show="Number(variance) > 0" class="font-semibold text-green-600" x-text="`${variance} {{ $baseUnit }}`"></span>
                            <span x-show="Number(variance) === 0" class="font-semibold text-gray-600" x-text="`0.000000 {{ $baseUnit }}`"></span>
                        </span>
                    </div>
                </div>
                <p x-show="saved" x-transition class="mt-3 text-xs font-semibold text-primary-700">Tersimpan</p>
            </div>
        @endforeach
    </div>

    <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
        <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
            <a href="{{ route('operations.opname.index') }}" class="sf-btn-secondary min-h-11 px-4 text-center">Simpan & Keluar</a>
            @if($session->status === 'DRAFT')
                <form method="POST" action="{{ route('operations.opname.submit', $session) }}">
                    @csrf
                    <button type="submit" class="sf-btn-primary min-h-11 w-full sm:w-auto px-4">Submit Approval</button>
                </form>
            @elseif($session->status === 'SUBMITTED')
                @can('approve_opname')
                    <form method="POST" action="{{ route('operations.opname.approve', $session) }}">
                        @csrf
                        <button type="submit" class="sf-btn-primary min-h-11 w-full sm:w-auto px-4">Approve & Proses</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function opnameItemCard(config) {
    return {
        url: config.url,
        variance: config.variance || '0.000000',
        varianceValue: config.varianceValue || '0.0000',
        physicalBase: config.physicalBase || '0.000000',
        wasCounted: config.wasCounted === true,
        qtyWhole: config.qtyWhole || '',
        qtyLoose: config.qtyLoose || '',
        saved: false,
        async save() {
            const response = await fetch(this.url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    qty_whole: this.qtyWhole || '0',
                    qty_loose: this.qtyLoose || '0',
                }),
            });

            if (!response.ok) return;

            const data = await response.json();
            this.variance = data.variance;
            this.varianceValue = data.variance_value;
            this.physicalBase = data.physical_qty_base;
            this.saved = true;

            if (!this.wasCounted) {
                this.wasCounted = true;
                this.$dispatch('item-counted', { counted: data.counted });
            }

            setTimeout(() => this.saved = false, 1200);
        },
    };
}
</script>
@endpush
@endsection
