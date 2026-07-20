@extends('layouts.app')

@section('title', 'Detail Opname')

@section('content')
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

    <div class="sticky top-0 z-10 -mx-4 border-y border-gray-100 bg-white px-4 py-3 lg:mx-0 lg:rounded-2xl lg:border">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[180px]">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <input type="text"
                       id="opname-search"
                       value="{{ $search }}"
                       placeholder="Cari bahan baku..."
                       class="sf-input pl-9 w-full text-sm min-h-11">
            </div>

            <select id="opname-category" class="sf-input text-sm w-auto min-h-11">
                <option value="">Semua Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <select id="opname-perpage" class="sf-input text-sm w-auto min-h-11">
                <option value="20" @selected($perPage === '20')>20 item</option>
                <option value="50" @selected($perPage === '50')>50 item</option>
                <option value="100" @selected($perPage === '100')>100 item</option>
                <option value="all" @selected($perPage === 'all')>Tampil Semua</option>
            </select>

            @if($roleFilter)
                <span class="inline-flex min-h-9 items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800">
                    <i class="ti ti-filter text-xs" aria-hidden="true"></i>
                    Dept. {{ $roleFilter }}
                </span>
            @endif

            <span class="ml-auto text-xs text-gray-400">
                {{ $paginator ? $paginator->total() : $items->count() }} item
            </span>
        </div>
    </div>

    <div class="space-y-3">
        @forelse($items as $opnameItem)
            @php
                $item = $opnameItem->item;
                $inventoryUnit = $item?->inventoryUnit?->abbreviation ?? $opnameItem->unit?->abbreviation ?? 'unit';
                $baseUnit = $item?->baseUnit?->abbreviation ?? 'base';
                $invRatio = (float) ($item?->inventory_ratio ?? 1);
                $sysQty   = (float) ($opnameItem->stok_sistem ?? 0);
            @endphp
            <div class="sf-card p-4"
                  x-data="opnameItemCard({
                     url: @js(route('operations.opname.update-item', [$session, $opnameItem])),
                     suggestionUrl: @js(route('api.stock-suggestion', ['item_id' => $opnameItem->item_id, 'outlet_id' => $session->outlet_id])),
                     variance: @js((string) $opnameItem->variance),
                    varianceValue: @js((string) $opnameItem->variance_value),
                    qtyWhole: @js((string) $opnameItem->physical_qty_whole),
                    qtyLoose: @js((string) $opnameItem->physical_qty_loose),
                    invRatio: @js($invRatio),
                    sysQty: @js($sysQty),
                     wasCounted: @js((bool) $opnameItem->is_counted)
                  })"
                  x-init="fetchSuggestion()">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $item?->name ?? '-' }}</p>
                        <p class="text-xs text-gray-500">{{ $item?->canonical_sku ?? '-' }}</p>
                    </div>
                    <span class="badge-draft">{{ $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-' }}</span>
                </div>

                <div class="mt-4 space-y-1.5">
                    <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm flex justify-between gap-3">
                        <span class="text-gray-500">Sistem (snapshot)</span>
                        <span class="font-semibold text-gray-900">{{ number_format((float) $opnameItem->system_qty_base, 2) }} {{ $baseUnit }}</span>
                    </div>
                    <div class="rounded-xl px-3 py-2 text-sm flex justify-between gap-3 {{ $sysQty > 0 ? 'bg-blue-50' : 'bg-gray-50' }}">
                        <span class="{{ $sysQty > 0 ? 'text-blue-600' : 'text-gray-500' }}">Stok Saat Ini</span>
                        <span class="font-semibold {{ $sysQty > 0 ? 'text-blue-700' : 'text-gray-400' }}">
                            {{ number_format($sysQty / ($invRatio ?: 1), 2) }} {{ $inventoryUnit }}
                            @if($sysQty > 0)
                                <span class="text-xs font-normal text-gray-400">({{ number_format($sysQty, 0) }} {{ $baseUnit }})</span>
                            @endif
                        </span>
                    </div>
                    @if($sysQty == 0)
                        <p class="text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1">
                            ⚠️ Stok sistem = 0. Jika ada stok fisik, input jumlah yang sebenarnya.
                        </p>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="sf-label">Fisik utuh ({{ $inventoryUnit }})</label>
                        <input type="text"
                               inputmode="decimal"
                               x-model="qtyWhole"
                               @input.debounce.500ms="save()"
                               placeholder="0"
                               class="sf-input text-base min-h-11"
                               @disabled($session->status !== 'DRAFT')>
                    </div>
                    <div>
                        <label class="sf-label">Fisik ecer ({{ $baseUnit }})</label>
                        <input type="text"
                               inputmode="decimal"
                               x-model="qtyLoose"
                               @input.debounce.500ms="save()"
                               placeholder="0"
                               class="sf-input text-base min-h-11"
                               @disabled($session->status !== 'DRAFT')>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
                        <span class="text-gray-500">Fisik base</span>
                        <span class="font-semibold text-gray-900"><span x-text="physicalBaseDisplay"></span> {{ $inventoryUnit }}</span>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
                        <span class="text-gray-500">Selisih</span>
                        <span>
                            <span x-show="Number(variance) < 0" class="font-semibold text-red-600" x-text="`${varianceDisplay} {{ $inventoryUnit }}`"></span>
                            <span x-show="Number(variance) > 0" class="font-semibold text-green-600" x-text="`${varianceDisplay} {{ $inventoryUnit }}`"></span>
                            <span x-show="Number(variance) === 0" class="font-semibold text-gray-600">0.00 {{ $inventoryUnit }}</span>
                        </span>
                    </div>
                </div>

                <div x-show="isOverStock" x-cloak class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                    <p class="text-xs font-semibold text-blue-700 flex items-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                        </svg>
                        Fisik melebihi stok sistem — pastikan input sudah benar.
                    </p>
                </div>

                <div x-show="suggestion" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3a6 6 0 00-3.6 10.8c.75.56 1.263 1.315 1.263 2.2h4.674c0-.885.513-1.64 1.263-2.2A6 6 0 0012 3z"/>
                            </svg>
                            Saran Order
                        </span>
                        <span class="text-xs text-amber-600"
                              x-text="suggestion && suggestion.days_remaining !== null ? suggestion.days_remaining.toFixed(1) + ' hari lagi habis' : 'Belum ada pola pemakaian'"></span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-amber-600">Rata-rata/hari</span>
                            <p class="font-semibold text-amber-900"
                               x-text="suggestion ? formatQty(suggestion.avg_daily_usage) + ' ' + suggestion.unit_abbreviation : ''"></p>
                        </div>
                        <div>
                            <span class="text-amber-600">Rekomendasi order</span>
                            <p class="text-sm font-bold text-amber-900"
                               x-text="suggestion ? formatQty(suggestion.recommended_order) + ' ' + suggestion.unit_abbreviation : ''"></p>
                        </div>
                    </div>

                    <template x-if="suggestion && suggestion.upcoming_events && suggestion.upcoming_events.length > 0">
                        <div class="mt-2 border-t border-amber-200 pt-2">
                            <p class="flex items-center gap-1.5 text-xs text-amber-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z"/>
                                </svg>
                                <span x-text="suggestion.upcoming_events[0].name"></span>
                                <span x-text="formatDemandChange(suggestion.upcoming_events[0].demand_change_pct)"></span>
                            </p>
                        </div>
                    </template>

                    <p x-show="suggestion && suggestion.is_critical" class="mt-2 text-xs font-bold text-red-600">
                        KRITIS - stok di bawah minimum.
                    </p>
                </div>

                <p x-show="saved" x-transition class="mt-3 text-xs font-semibold text-primary-700">Tersimpan</p>
            </div>
        @empty
            <x-sf.empty-state
                icon="OPN"
                title="Item tidak ditemukan"
                description="Coba ubah kata kunci, kategori, atau jumlah item yang ditampilkan."
            />
        @endforelse
    </div>

    @if($paginator)
        <div class="rounded-2xl border border-gray-100 bg-white p-4">
            {{ $paginator->links() }}
        </div>
    @endif

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
(function () {
    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn(...args);
            }, delay);
        };
    }

    function buildUrl(params) {
        const url = new URL(window.location.href);
        Object.entries(params).forEach(function ([key, value]) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        });
        url.searchParams.delete('page');
        return url.toString();
    }

    const search = document.getElementById('opname-search');
    if (search) {
        search.addEventListener('input', debounce(function (event) {
            window.location = buildUrl({ q: event.target.value });
        }, 350));
    }

    const category = document.getElementById('opname-category');
    if (category) {
        category.addEventListener('change', function (event) {
            window.location = buildUrl({ category_id: event.target.value });
        });
    }

    const perPage = document.getElementById('opname-perpage');
    if (perPage) {
        perPage.addEventListener('change', function (event) {
            window.location = buildUrl({ per_page: event.target.value });
        });
    }
})();

function opnameItemCard(config) {
    return {
        url: config.url,
        suggestionUrl: config.suggestionUrl,
        variance: config.variance || '0.000000',
        varianceValue: config.varianceValue || '0.0000',
        wasCounted: config.wasCounted === true,
        qtyWhole: parseFloat(config.qtyWhole) > 0 ? String(parseFloat(config.qtyWhole)) : '',
        qtyLoose: parseFloat(config.qtyLoose) > 0 ? String(parseFloat(config.qtyLoose)) : '',
        invRatio: parseFloat(config.invRatio) || 1,
        sysQty: parseFloat(config.sysQty) || 0,
        saved: false,
        suggestion: null,
        get physicalBase() {
            return (parseFloat(this.qtyWhole) || 0) * this.invRatio + (parseFloat(this.qtyLoose) || 0);
        },
        get physicalBaseDisplay() {
            return (this.physicalBase / (this.invRatio || 1)).toFixed(2);
        },
        get varianceDisplay() {
            return (parseFloat(this.variance) / (this.invRatio || 1)).toFixed(2);
        },
        get isOverStock() {
            return this.sysQty > 0 && this.physicalBase > this.sysQty;
        },
        async fetchSuggestion() {
            try {
                const response = await fetch(this.suggestionUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) return;

                const data = await response.json();
                this.suggestion = data.has_config ? data : null;
            } catch (error) {
                this.suggestion = null;
            }
        },
        formatQty(value) {
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(Number(value || 0));
        },
        formatDemandChange(value) {
            const change = Number(value || 0);
            if (change === 0) return '';
            return change > 0 ? `+${change}%` : `${change}%`;
        },
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
