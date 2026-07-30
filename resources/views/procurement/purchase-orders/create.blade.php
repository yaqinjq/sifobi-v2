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
<div class="px-4 pt-4 pb-32 lg:px-6 lg:pb-12 max-w-3xl mx-auto w-full"
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

    {{-- Simpan button (fixed bottom) --}}
    <div class="fixed inset-x-0 bottom-0 z-30 bg-white border-t border-gray-100 px-4 py-3"
         style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
        <button type="button"
                @click="openConfirm()"
                :disabled="!hasItems"
                :class="hasItems ? '' : 'opacity-40 cursor-not-allowed'"
                class="sf-btn-primary w-full">
            Simpan PO
        </button>
    </div>

    {{-- Konfirmasi Modal --}}
    <div x-show="showConfirm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-4 bg-black/40"
         @keydown.escape.window="showConfirm = false"
         x-cloak>

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             @click.stop>
            <div class="p-5">
                <h3 class="font-heading font-bold text-gray-900 text-lg mb-3">Konfirmasi PO</h3>

                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Outlet</span>
                        <span class="font-medium text-gray-800">{{ $outlet?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Departemen</span>
                        <span class="font-medium text-gray-800">{{ $department?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Tanggal</span>
                        <span class="font-medium text-gray-800" x-text="date"></span>
                    </div>
                    <div x-show="isPlanned" class="flex justify-between text-sm">
                        <span class="text-amber-600">Mode</span>
                        <span class="font-semibold text-amber-600">Plan Order</span>
                    </div>
                </div>

                <div class="rounded-xl bg-gray-50 px-3 py-3 mb-4 space-y-1.5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">PO yang akan dibuat:</p>
                    <template x-for="tab in tabs.filter(t => t.items.length > 0)" :key="tab.type">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 mb-1" x-text="tab.label"></p>
                            <template x-for="row in tab.items.filter(i => parseFloat(i.qty) > 0)" :key="row.item_id">
                                <div class="flex justify-between text-xs text-gray-600 py-0.5 pl-2">
                                    <span x-text="row.name" class="truncate flex-1 mr-2"></span>
                                    <span class="shrink-0">
                                        <span x-text="row.qty"></span>
                                        <span x-text="row.unit_code" class="ml-1 text-gray-400"></span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="submitError" x-cloak
                     class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700 mb-3"
                     x-text="submitError"></div>

                <div class="flex gap-3">
                    <button type="button"
                            @click="showConfirm = false"
                            :disabled="submitting"
                            class="sf-btn-secondary flex-1">
                        Batal
                    </button>
                    <button type="button"
                            @click="submitBatch()"
                            :disabled="submitting"
                            class="sf-btn-primary flex-1 flex items-center justify-center gap-2">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="submitting ? 'Menyimpan...' : 'Ya, Simpan'"></span>
                    </button>
                </div>
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
