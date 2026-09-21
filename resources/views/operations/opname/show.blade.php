@extends('layouts.app')

@section('title', 'Detail Opname')

@section('content')
<x-sf.page-header title="Opname {{ optional($session->opname_date)->format('d M Y') }}" subtitle="{{ $session->outlet?->name ?? '-' }}" back="{{ route('operations.opname.index') }}" />

@php
    $viewModeUrl = fn (string $mode): string => request()->fullUrlWithQuery(array_merge(
        ['view_mode' => $mode],
        $mode === 'category' ? ['sort_mode' => 'form'] : []
    ));
@endphp

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4"
     x-data="{ counted: {{ $counted }}, total: {{ $total }} }"
     @item-counted="counted = $event.detail.counted">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

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
        <form method="GET" action="{{ route('operations.opname.show', $session) }}" class="flex flex-wrap items-center gap-2" id="opname-filter-form">
            <div class="relative flex-1 min-w-[180px]">
                <button type="submit"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-700 transition-colors text-sm"
                        aria-label="Cari">
                    <i class="ti ti-search" aria-hidden="true"></i>
                </button>
                <input type="search"
                       name="q"
                       id="opname-search"
                       value="{{ $search }}"
                       placeholder="Cari bahan baku..."
                       class="sf-input pl-9 w-full text-sm min-h-11">
            </div>

            <select name="category_id" id="opname-category" class="sf-input text-sm w-auto min-h-11" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>
                        {{ $category->parent_id ? '— '.$category->name : $category->name }}
                    </option>
                @endforeach
            </select>

            <select name="sort_mode" id="opname-sort-mode" class="sf-input text-sm w-auto min-h-11" onchange="this.form.submit()">
                <option value="az" @selected($sortMode === 'az')>Urutkan: A-Z</option>
                <option value="form" @selected($sortMode === 'form')>Urutkan: Sesuai Form</option>
            </select>

            <select name="per_page" id="opname-perpage" class="sf-input text-sm w-auto min-h-11" onchange="this.form.submit()" @disabled($sortMode === 'form')>
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

            <span class="text-xs text-gray-400">
                {{ $paginator ? $paginator->total() : $items->count() }} item
            </span>

            @if($search !== '' || $categoryId !== '')
                <a href="{{ route('operations.opname.show', $session) }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors"
                   title="Reset filter">
                    <i class="ti ti-x text-xs" aria-hidden="true"></i>
                    <span>Reset</span>
                </a>
            @endif
        </form>
    </div>

    <div class="flex items-center gap-1.5 flex-wrap">
        <a href="{{ $viewModeUrl('card') }}" class="{{ $viewMode === 'card' ? 'sf-btn-primary' : 'sf-btn-secondary' }} text-xs px-3 py-1.5 min-h-9">
            <i class="ti ti-layout-grid text-sm" aria-hidden="true"></i> Card
        </a>
        <a href="{{ $viewModeUrl('list') }}" class="{{ $viewMode === 'list' ? 'sf-btn-primary' : 'sf-btn-secondary' }} text-xs px-3 py-1.5 min-h-9">
            <i class="ti ti-list text-sm" aria-hidden="true"></i> List
        </a>
        <a href="{{ $viewModeUrl('category') }}" class="{{ $viewMode === 'category' ? 'sf-btn-primary' : 'sf-btn-secondary' }} text-xs px-3 py-1.5 min-h-9">
            <i class="ti ti-category text-sm" aria-hidden="true"></i> Per Kategori
        </a>
        <a href="{{ $viewModeUrl('zoom') }}" class="hidden lg:inline-flex {{ $viewMode === 'zoom' ? 'sf-btn-primary' : 'sf-btn-secondary' }} text-xs px-3 py-1.5 min-h-9">
            <i class="ti ti-zoom-scan text-sm" aria-hidden="true"></i> Zoom
        </a>
    </div>

    <div x-data="opnameViewSwitcher({ mode: @js($viewMode), itemIds: @js($items->pluck('id')->values()) })">
        @if($viewMode === 'zoom')
            {{-- Zoom Morph: grid ringkas item -> fokus 1 item dengan transisi
                 native browser (document.startViewTransition), khusus desktop
                 dan otomatis fallback tanpa animasi di browser yang tidak
                 dukung API ini. --}}
            <div x-show="!zoomFocusId" class="grid grid-cols-3 sm:grid-cols-4 xl:grid-cols-6 gap-3">
                @forelse($items as $opnameItem)
                    @php $zoomItem = $opnameItem->item; @endphp
                    <button type="button" @click="focusItem({{ $opnameItem->id }})"
                            :style="`view-transition-name: vt-opname-item-{{ $opnameItem->id }}`"
                            class="sf-card p-3 text-left hover:shadow-md transition-shadow">
                        <div class="w-full aspect-square rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden mb-2">
                            @if($zoomItem?->photo)
                                <img src="{{ asset('storage/'.$zoomItem->photo) }}" alt="{{ $zoomItem->name }}" class="w-full h-full object-cover">
                            @else
                                <i class="ti ti-photo text-2xl text-gray-300" aria-hidden="true"></i>
                            @endif
                        </div>
                        <p class="text-xs font-semibold text-gray-900 truncate">{{ $zoomItem?->name ?? '-' }}</p>
                        <p class="text-[11px] {{ $opnameItem->is_counted ? 'text-green-600' : 'text-gray-400' }} mt-0.5">
                            <i class="ti {{ $opnameItem->is_counted ? 'ti-circle-check' : 'ti-circle-dashed' }}" aria-hidden="true"></i>
                            {{ $opnameItem->is_counted ? 'Terhitung' : 'Belum' }}
                        </p>
                    </button>
                @empty
                    <x-sf.empty-state
                        icon="OPN"
                        title="Item tidak ditemukan"
                        description="Coba ubah kata kunci, kategori, atau jumlah item yang ditampilkan."
                    />
                @endforelse
            </div>

            <div x-show="zoomFocusId" x-cloak class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" @click="unfocusItem()" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9">
                        <i class="ti ti-arrow-left text-sm" aria-hidden="true"></i> Kembali ke grid
                    </button>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="prevItem()" class="sf-btn-secondary text-xs px-2.5 py-1.5 min-h-9">
                            <i class="ti ti-chevron-left text-sm" aria-hidden="true"></i>
                        </button>
                        <button type="button" @click="nextItem()" class="sf-btn-secondary text-xs px-2.5 py-1.5 min-h-9">
                            <i class="ti ti-chevron-right text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="max-w-md mx-auto">
                    @foreach($items as $opnameItem)
                        @include('operations.opname.partials.item-card', ['opnameItem' => $opnameItem])
                    @endforeach
                </div>
            </div>
        @elseif($viewMode === 'category')
            <div class="space-y-6">
                @forelse($groupedItems as $categoryLabel => $groupItems)
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-1.5">
                            <i class="ti ti-category text-sm" aria-hidden="true"></i>
                            {{ $categoryLabel }}
                            <span class="text-gray-300 font-normal normal-case">({{ $groupItems->count() }} item)</span>
                        </h3>
                        <div class="space-y-3 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-4 lg:items-start">
                            @foreach($groupItems as $opnameItem)
                                @include('operations.opname.partials.item-card', ['opnameItem' => $opnameItem])
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-sf.empty-state
                        icon="OPN"
                        title="Item tidak ditemukan"
                        description="Coba ubah kata kunci, kategori, atau jumlah item yang ditampilkan."
                    />
                @endforelse
            </div>
        @else
            <div class="{{ $viewMode === 'list' ? 'space-y-3' : 'space-y-3 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-4 lg:items-start' }}">
                @forelse($items as $opnameItem)
                    @include('operations.opname.partials.item-card', ['opnameItem' => $opnameItem])
                @empty
                    <x-sf.empty-state
                        icon="OPN"
                        title="Item tidak ditemukan"
                        description="Coba ubah kata kunci, kategori, atau jumlah item yang ditampilkan."
                    />
                @endforelse
            </div>
        @endif
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
                <form id="opname-submit-form" method="POST" action="{{ route('operations.opname.submit', $session) }}">
                    @csrf
                    <button type="button"
                            onclick="checkBeforeSubmit(event)"
                            class="sf-btn-primary min-h-11 w-full sm:w-auto px-4">Submit Approval</button>
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

{{-- Modal konfirmasi submit opname --}}
<div id="opname-confirm-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4"
     role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="ti ti-alert-triangle text-amber-600 text-lg" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-base">Konfirmasi Submit Opname</h3>
                <p class="text-xs text-gray-500">Ditemukan anomali yang perlu diperhatikan</p>
            </div>
        </div>
        <div id="anomali-list" class="mb-5 space-y-2 max-h-48 overflow-y-auto"></div>
        <p class="text-sm text-gray-700 mb-5 font-medium">Apakah Anda yakin data opname sudah benar dan ingin menyimpan?</p>
        <div class="flex gap-3">
            <button type="button"
                    onclick="closeOpnameModal()"
                    class="flex-1 py-2.5 px-4 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                Periksa Ulang
            </button>
            <button type="button"
                    onclick="confirmOpnameSubmit()"
                    class="flex-1 py-2.5 px-4 bg-green-700 rounded-xl text-sm font-semibold text-white hover:bg-green-600 transition-colors">
                Ya, Simpan Opname
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function opnameItemCard(config) {
    return {
        url: config.url,
        syncUrl: config.syncUrl,
        suggestionUrl: config.suggestionUrl,
        variance: config.variance || '0.000000',
        varianceValue: config.varianceValue || '0.0000',
        wasCounted: config.wasCounted === true,
        qtyWhole: parseFloat(config.qtyWhole) > 0 ? String(parseFloat(config.qtyWhole)) : '',
        qtyLoose: parseFloat(config.qtyLoose) > 0 ? String(parseFloat(config.qtyLoose)) : '',
        invRatio: parseFloat(config.invRatio) || 1,
        sysQtyBase: parseFloat(config.sysQtyBase) || 0,
        sysQtyLive: parseFloat(config.sysQtyLive) || 0,
        sysQty: parseFloat(config.sysQtyBase) || 0,
        decimals: config.decimals ?? 2,
        saved: false,
        syncing: false,
        suggestion: null,
        get isBaselineStale() {
            return Math.abs(this.sysQtyLive - this.sysQtyBase) > 0.000001;
        },
        async syncBaseline() {
            this.syncing = true;

            try {
                const response = await fetch(this.syncUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (!response.ok) return;

                const data = await response.json();
                this.sysQtyBase = parseFloat(data.system_qty_base) || 0;
                this.sysQty = this.sysQtyBase;
                this.sysQtyLive = this.sysQtyBase;
                this.variance = data.variance;
                this.varianceValue = data.variance_value;
            } finally {
                this.syncing = false;
            }
        },
        get physicalBase() {
            return (parseFloat(this.qtyWhole) || 0) * this.invRatio + (parseFloat(this.qtyLoose) || 0);
        },
        get physicalBaseDisplay() {
            return (this.physicalBase / (this.invRatio || 1)).toFixed(this.decimals);
        },
        // Real-time variance: sistem (snapshot) - fisik. Positif = kurang fisik, negatif = lebih fisik.
        get liveVariance() {
            return this.sysQtyBase - this.physicalBase;
        },
        get liveVarianceDisplay() {
            return (this.liveVariance / (this.invRatio || 1)).toFixed(this.decimals);
        },
        get varianceDisplay() {
            return (parseFloat(this.variance) / (this.invRatio || 1)).toFixed(this.decimals);
        },
        get hasInput() {
            return (parseFloat(this.qtyWhole) || 0) > 0 || (parseFloat(this.qtyLoose) || 0) > 0;
        },
        get isOverSystemStock() {
            return this.sysQtyBase > 0 && this.physicalBase > this.sysQtyBase;
        },
        get isSuspiciousWhenZero() {
            return this.sysQtyBase === 0 && this.physicalBase > 0;
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

function opnameViewSwitcher(config) {
    return {
        mode: config.mode || 'card',
        itemIds: config.itemIds || [],
        zoomFocusId: null,
        focusItem(id) {
            const run = () => { this.zoomFocusId = id; };
            document.startViewTransition ? document.startViewTransition(run) : run();
        },
        unfocusItem() {
            const run = () => { this.zoomFocusId = null; };
            document.startViewTransition ? document.startViewTransition(run) : run();
        },
        nextItem() {
            const idx = this.itemIds.indexOf(this.zoomFocusId);
            if (idx > -1 && idx < this.itemIds.length - 1) {
                this.focusItem(this.itemIds[idx + 1]);
            }
        },
        prevItem() {
            const idx = this.itemIds.indexOf(this.zoomFocusId);
            if (idx > 0) {
                this.focusItem(this.itemIds[idx - 1]);
            }
        },
    };
}

function checkBeforeSubmit(event) {
    event.preventDefault();
    var anomalies = [];
    document.querySelectorAll('[data-opname-item]').forEach(function (el) {
        var itemName    = el.dataset.itemName || 'Item';
        var physDisplay = el.dataset.physicalDisplay || '-';
        var itemId      = el.id.replace('opname-item-', '');
        if (el.dataset.overSystem === 'true') {
            anomalies.push({
                itemId: itemId,
                icon: 'ti-info-circle',
                color: 'text-blue-600',
                bg: 'bg-blue-50 border-blue-200',
                message: '<strong>' + itemName + '</strong>: Fisik (' + physDisplay + ') lebih dari stok sistem',
            });
        }
        if (el.dataset.suspicious === 'true') {
            anomalies.push({
                itemId: itemId,
                icon: 'ti-alert-triangle',
                color: 'text-orange-600',
                bg: 'bg-orange-50 border-orange-200',
                message: '<strong>' + itemName + '</strong>: Ada stok fisik (' + physDisplay + ') padahal stok sistem = 0',
            });
        }
    });
    if (anomalies.length === 0) {
        document.getElementById('opname-submit-form').submit();
        return;
    }
    var list = document.getElementById('anomali-list');
    list.innerHTML = anomalies.map(function (a) {
        return '<div class="flex items-start gap-2 p-2.5 rounded-xl border ' + a.bg +
            ' cursor-pointer hover:opacity-80 transition-opacity"' +
            ' onclick="scrollToOpnameItem(\'' + a.itemId + '\')" title="Klik untuk ke item ini">' +
            '<i class="ti ' + a.icon + ' ' + a.color + ' flex-shrink-0 mt-0.5 text-sm" aria-hidden="true"></i>' +
            '<div class="flex-1">' +
            '<p class="text-xs text-gray-700">' + a.message + '</p>' +
            '<p class="text-xs text-gray-400 mt-0.5">Klik untuk ke item ini →</p>' +
            '</div>' +
            '</div>';
    }).join('');
    var modal = document.getElementById('opname-confirm-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeOpnameModal() {
    var modal = document.getElementById('opname-confirm-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function confirmOpnameSubmit() {
    closeOpnameModal();
    document.getElementById('opname-submit-form').submit();
}

function scrollToOpnameItem(itemId) {
    closeOpnameModal();
    var el = document.getElementById('opname-item-' + itemId);
    if (!el) { return; }
    var offset = 120;
    var top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: top, behavior: 'smooth' });
    el.classList.add('ring-2', 'ring-amber-400', 'ring-offset-2', 'transition-all');
    setTimeout(function () {
        el.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-2', 'transition-all');
    }, 2000);
}

(function () {
    var modal = document.getElementById('opname-confirm-modal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) { closeOpnameModal(); }
        });
    }
})();
</script>
@endpush
@endsection
