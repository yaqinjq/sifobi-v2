@extends('layouts.app')

@section('title', 'Buat Purchase Order')

@section('topbar')
<x-sf.page-header
    title="Buat Purchase Order"
    subtitle="{{ $outlet?->name ?? 'Pilih outlet' }}"
    back="{{ route('procurement.purchase-orders.index') }}"
/>
@endsection

@section('content')
<div class="px-4 pt-4 pb-44 lg:px-6 lg:pb-4 max-w-3xl mx-auto w-full"
     x-data="poForm()"
     x-init="init()">

    {{-- Info header (read-only) --}}
    <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 mb-4 space-y-1.5">
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-400 w-28 shrink-0">Outlet</span>
            <span class="font-semibold text-gray-800">{{ $outlet?->name ?? '—' }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-400 w-28 shrink-0">Departemen</span>
            <span class="font-semibold text-gray-800">{{ $department?->name ?? '—' }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-400 w-28 shrink-0">Tanggal Order</span>
            <div class="flex-1">
                <input type="date"
                       x-model="date"
                       min="{{ $today }}"
                       class="sf-input text-sm py-1 px-2 h-auto"
                       @change="onDateChange()" />
            </div>
        </div>
        <div x-show="isPlanned" x-cloak
             class="mt-1 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
            PO ini adalah <strong>plan order</strong> — akan otomatis diajukan ke PIC pada tanggal yang dipilih.
        </div>
    </div>

    {{-- Catatan --}}
    <div class="mb-4">
        <textarea x-model="notes"
                  rows="2"
                  placeholder="Catatan tambahan (opsional)..."
                  class="sf-input text-sm resize-none w-full"></textarea>
    </div>

    {{-- Tabs tujuan PO --}}
    <div class="space-y-4">

        {{-- Tab headers --}}
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
            <template x-for="tab in tabs" :key="tab.type">
                <button type="button"
                        @click="activeTab = tab.type"
                        :class="activeTab === tab.type
                            ? 'bg-white shadow-sm text-gray-900 font-semibold'
                            : 'text-gray-500'"
                        class="flex-1 rounded-lg py-2 text-sm transition-all flex items-center justify-center gap-1.5 min-w-0">
                    <span class="truncate" x-text="tab.label"></span>
                    <span x-show="tab.items.length > 0"
                          x-text="tab.items.length"
                          class="shrink-0 bg-primary-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center"></span>
                </button>
            </template>
        </div>

        {{-- Tab content — satu div per tab, show/hide tanpa x-for ganda --}}
        <template x-for="tab in tabs" :key="tab.type">
            <div x-show="activeTab === tab.type" x-cloak>

                {{-- Live search (Google-style) --}}
                <div class="relative mb-3">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                    </div>

                    <input type="text"
                           :value="tab.search"
                           @input.debounce.350ms="tab.search = $event.target.value; doSearch(tab)"
                           @focus="if (tab.search.length >= 1) doSearch(tab)"
                           @keydown.escape="closeResults(tab)"
                           placeholder="Ketik nama item..."
                           class="sf-input pl-9 text-sm w-full"
                           autocomplete="off"
                           spellcheck="false" />

                    {{-- Spinner --}}
                    <div x-show="tab.loading"
                         class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>

                    {{-- Dropdown hasil pencarian --}}
                    <div x-show="tab.results.length > 0"
                         x-cloak
                         class="absolute z-30 left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden max-h-64 overflow-y-auto">
                        <template x-for="result in tab.results" :key="result.id">
                            <button type="button"
                                    @mousedown.prevent="selectItem(tab, result)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-primary-50 flex items-center gap-3 border-b border-gray-50 last:border-0 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate" x-text="result.name"></p>
                                    <p class="text-xs text-gray-400 mt-0.5" x-text="result.sku"></p>
                                </div>
                                <span class="shrink-0 text-xs font-semibold text-primary-700 bg-primary-50 rounded px-2 py-0.5"
                                      x-text="result.unit_code"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Daftar item yang sudah dipilih --}}
                <div x-show="tab.items.length > 0" class="space-y-2 mb-3">
                    <template x-for="(row, rowIdx) in tab.items" :key="row.item_id">
                        <div class="rounded-xl border border-gray-100 bg-white px-3 py-2.5 flex items-center gap-3 shadow-sm">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate" x-text="row.name"></p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <input type="number"
                                           :value="row.qty"
                                           @input="row.qty = $event.target.value"
                                           min="0.001"
                                           step="0.001"
                                           placeholder="Jumlah"
                                           class="sf-input text-sm py-1 px-2 h-auto w-28" />
                                    <span class="text-sm text-gray-600 font-semibold" x-text="row.unit_code"></span>
                                </div>
                            </div>
                            <button type="button"
                                    @click="removeItem(tab, rowIdx)"
                                    class="shrink-0 text-red-400 hover:text-red-600 transition-colors p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div x-show="tab.items.length === 0" class="text-center py-10 text-gray-400 text-sm">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    Ketik nama item untuk mencari
                </div>
            </div>
        </template>

        {{-- Ringkasan per tab --}}
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Ringkasan</p>
            <template x-for="tab in tabs" :key="tab.type">
                <div class="flex items-center justify-between text-sm py-0.5">
                    <span class="text-gray-600" x-text="tab.label"></span>
                    <span :class="tab.items.length > 0 ? 'font-semibold text-primary-700' : 'text-gray-400'">
                        <span x-text="tab.items.length"></span> item
                    </span>
                </div>
            </template>
        </div>

    </div>

    {{-- Simpan button --}}
    <div id="po-submit-bar" class="fixed inset-x-0 z-50 bg-white border-t border-gray-100 px-4 py-3">
        <button type="button"
                @click="openConfirm()"
                :disabled="!hasItems"
                :class="hasItems ? '' : 'opacity-40 cursor-not-allowed'"
                class="sf-btn-primary w-full min-h-12 text-base font-semibold">
            Simpan PO
        </button>
    </div>
    <style>
        /* Mobile: letakkan DI ATAS bottom nav (h-16 = 4rem) + safe area iPhone */
        #po-submit-bar {
            bottom: calc(4rem + env(safe-area-inset-bottom));
        }
        /* Desktop (lg 1024px+): sticky dalam alur konten, tidak overlap sidebar */
        @media (min-width: 1024px) {
            #po-submit-bar {
                position: sticky;
                bottom: 0;
                left: auto;
                right: auto;
                margin-left: -1.5rem;
                margin-right: -1.5rem;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }
    </style>

    {{-- Konfirmasi Modal --}}
    <div x-show="showConfirm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="showConfirm = false"
         x-cloak>

        <div class="bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl w-full sm:max-w-md overflow-hidden"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-8"
             x-transition:enter-end="opacity-100 translate-y-0"
             @click.stop>

            {{-- Header --}}
            <div class="bg-primary-700 px-6 py-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-white text-base leading-tight">Konfirmasi Purchase Order</h3>
                    <p class="text-primary-200 text-xs mt-0.5">Periksa kembali sebelum dikirim</p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">

                {{-- Info Ringkas --}}
                <div class="rounded-2xl border border-gray-100 bg-gray-50 divide-y divide-gray-100">
                    @if($outlet?->name)
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            Outlet
                        </span>
                        <span class="text-sm font-semibold text-gray-800">{{ $outlet->name }}</span>
                    </div>
                    @endif
                    @if($department?->name)
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Departemen
                        </span>
                        <span class="text-sm font-semibold text-gray-800">{{ $department->name }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Tanggal Order
                        </span>
                        <span class="text-sm font-semibold text-gray-800" x-text="date"></span>
                    </div>
                    <div x-show="isPlanned" class="flex items-center justify-between px-4 py-3 bg-amber-50 rounded-b-2xl">
                        <span class="text-xs text-amber-600 font-medium flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Mode
                        </span>
                        <span class="text-sm font-bold text-amber-600">Plan Order</span>
                    </div>
                </div>

                {{-- Daftar Item per PO --}}
                <div>
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Item yang dipesan</p>
                    <div class="space-y-2">
                        <template x-for="tab in tabs.filter(t => t.items.filter(i => parseFloat(i.qty) > 0).length > 0)" :key="tab.type">
                            <div class="rounded-2xl border border-gray-100 overflow-hidden">
                                {{-- Group label --}}
                                <div class="bg-primary-50 px-4 py-2.5 flex items-center justify-between">
                                    <p class="text-xs font-bold text-primary-800" x-text="tab.label"></p>
                                    <span class="text-[10px] font-semibold bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full"
                                          x-text="tab.items.filter(i => parseFloat(i.qty) > 0).length + ' item'"></span>
                                </div>
                                {{-- Items --}}
                                <div class="divide-y divide-gray-50">
                                    <template x-for="(row, idx) in tab.items.filter(i => parseFloat(i.qty) > 0)" :key="row.item_id">
                                        <div class="flex items-center justify-between px-4 py-2.5 gap-3">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="w-5 h-5 rounded-full bg-gray-100 text-gray-400 text-[10px] font-bold flex items-center justify-center shrink-0"
                                                      x-text="idx + 1"></span>
                                                <span class="text-sm text-gray-800 truncate" x-text="row.name"></span>
                                            </div>
                                            <div class="shrink-0 flex items-baseline gap-1">
                                                <span class="text-sm font-bold text-primary-700" x-text="row.qty"></span>
                                                <span class="text-xs text-gray-400 font-medium" x-text="row.unit_code"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Error --}}
                <div x-show="submitError" x-cloak
                     class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="submitError"></span>
                </div>

            </div>

            {{-- Action Buttons --}}
            <div class="px-6 pb-6 pt-2 flex gap-3">
                <button type="button"
                        @click="showConfirm = false"
                        :disabled="submitting"
                        class="sf-btn-secondary flex-1 min-h-12">
                    Batal
                </button>
                <button type="button"
                        @click="submitBatch()"
                        :disabled="submitting"
                        class="sf-btn-primary flex-1 min-h-12 flex items-center justify-center gap-2">
                    <svg x-show="submitting" class="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg x-show="!submitting" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <span x-text="submitting ? 'Menyimpan...' : 'Kirim PO'"></span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function poForm() {
    const typeLabels = @json($typeLabels);
    const today      = '{{ $today }}';

    return {
        tabs:        [],
        activeTab:   '',
        date:        today,
        notes:       '',
        isPlanned:   false,
        showConfirm: false,
        submitting:  false,
        submitError: '',

        init() {
            this.tabs = Object.entries(typeLabels).map(([type, label]) => ({
                type,
                label,
                items:   [],
                search:  '',
                results: [],
                loading: false,
            }));
            this.activeTab = this.tabs.length > 0 ? this.tabs[0].type : '';
        },

        onDateChange() {
            this.isPlanned = this.date > today;
        },

        async doSearch(tab) {
            const q = tab.search.trim();
            if (q.length < 1) {
                tab.results = [];
                return;
            }
            tab.loading = true;
            try {
                const url = `/api/items/search-for-po?q=${encodeURIComponent(q)}&po_type=${encodeURIComponent(tab.type)}`;
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers:     { Accept: 'application/json' },
                });
                if (res.ok) {
                    tab.results = await res.json();
                } else {
                    tab.results = [];
                }
            } catch {
                tab.results = [];
            }
            tab.loading = false;
        },

        closeResults(tab) {
            tab.results = [];
        },

        selectItem(tab, result) {
            // Tutup dropdown dulu
            tab.results = [];
            tab.search  = '';

            // Jangan tambah duplikat
            if (tab.items.find(i => i.item_id === result.id)) return;

            tab.items.push({
                item_id:   result.id,
                name:      result.name,
                unit_id:   result.unit_id,
                unit_code: result.unit_code,  // Otomatis dari satuan inventory master item
                qty:       '',
            });
        },

        removeItem(tab, index) {
            tab.items.splice(index, 1);
        },

        get hasItems() {
            return this.tabs.some(t => t.items.some(i => parseFloat(i.qty) > 0));
        },

        openConfirm() {
            if (!this.hasItems) return;
            this.submitError = '';
            this.showConfirm = true;
        },

        async submitBatch() {
            this.submitting  = true;
            this.submitError = '';

            const tabsPayload = {};
            this.tabs.forEach(tab => {
                const valid = tab.items.filter(i => parseFloat(i.qty) > 0);
                if (valid.length > 0) {
                    tabsPayload[tab.type] = valid.map(i => ({
                        item_id:     i.item_id,
                        unit_id:     i.unit_id,
                        qty_ordered: parseFloat(i.qty),
                    }));
                }
            });

            try {
                const res = await fetch('{{ route("procurement.purchase-orders.store-batch") }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept:         'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        needed_at: this.date,
                        notes:     this.notes,
                        tabs:      tabsPayload,
                    }),
                });

                const data = await res.json();

                if (!res.ok) {
                    const errors = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : null;
                    this.submitError = errors || data.message || data.error || 'Terjadi kesalahan. Coba lagi.';
                    this.submitting  = false;
                    return;
                }

                window.location.href = data.redirect;
            } catch {
                this.submitError = 'Tidak dapat terhubung ke server. Periksa koneksi Anda.';
                this.submitting  = false;
            }
        },
    };
}
</script>
@endpush
