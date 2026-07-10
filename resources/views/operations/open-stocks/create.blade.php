@extends('layouts.app')

@section('title', 'Input Open Stock')
@section('hide-bottom-nav', 'true')

@section('topbar')
<x-sf.page-header title="Input Open Stock" subtitle="Batch stok awal" back="{{ route('operations.open-stocks.index') }}" />
@endsection

@php
    $selectedOutletId = auth()->user()->outlet_id ?: $outlets->first()?->id;
@endphp

@section('content')
<div
    x-data="openStockBatch({
        storeUrl: @js(route('operations.open-stocks.store')),
        searchUrl: @js(route('operations.open-stocks.item-search')),
        suggestionUrl: @js(route('api.stock-suggestion')),
        indexUrl: @js(route('operations.open-stocks.index')),
        selectedOutletId: @js((string) $selectedOutletId),
        today: @js(now()->toDateString()),
        csrf: @js(csrf_token()),
        dailyTarget: @js(\App\Modules\Operations\Models\OpenStock::TARGET_OUTLET_DAILY),
        warehouseTarget: @js(\App\Modules\Operations\Models\OpenStock::TARGET_OUTLET_WAREHOUSE),
    })"
    x-init="init()"
    class="pb-32"
>
    <div class="px-4 pt-4 lg:px-6 w-full space-y-4">
        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3">
            <p class="text-sm font-semibold text-blue-900">Stok Harian Outlet diposting ke stok harian outlet.</p>
            <p class="text-sm text-blue-800 mt-1">Gudang Utama diposting ke gudang outlet dengan satuan pembelian.</p>
        </div>

        <x-sf.card title="Header Batch">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-sf.form-group label="Tanggal" for="business_date" :required="true">
                    <input id="business_date" type="date" x-model="form.business_date" class="sf-input text-base" required>
                </x-sf.form-group>

                <x-sf.form-group label="Outlet" for="outlet_id" :required="true">
                    <select id="outlet_id" x-model="form.outlet_id" @change="refreshSuggestions()" class="sf-input text-base" required>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </x-sf.form-group>
            </div>

            <div class="mt-4 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3">
                <span class="sf-label text-primary-900">Target Stok Dipilih per Baris Item</span>
                <p class="mt-1 text-sm text-primary-800">
                    Satu bahan bisa disimpan hanya di stok harian outlet, hanya di gudang utama, atau di keduanya.
                </p>
            </div>

            <div class="mt-4">
                <x-sf.form-group label="Catatan Batch" for="batch_notes">
                    <input id="batch_notes" type="text" x-model="form.batch_notes" class="sf-input text-base" maxlength="500" placeholder="Opsional">
                </x-sf.form-group>
            </div>
        </x-sf.card>

        <x-sf.card title="Baris Item">
            <div class="hidden md:grid grid-cols-12 gap-3 px-1 pb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <span class="col-span-2">Departemen</span>
                <span class="col-span-3">Bahan Baku</span>
                <span class="col-span-1">Satuan</span>
                <span class="col-span-2">Target</span>
                <span class="col-span-3">Qty</span>
                <span class="col-span-1 text-center">Aksi</span>
            </div>

            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="row.id">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2 md:hidden">
                            <span class="text-xs font-bold text-gray-400" x-text="'Baris ' + (index + 1)"></span>
                            <button type="button"
                                    @click="removeRow(index)"
                                    class="sf-icon-action sf-icon-danger-soft"
                                    title="Hapus baris"
                                    aria-label="Hapus baris">
                                <i class="ti ti-x text-sm" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                            <div class="md:col-span-2">
                                <label class="md:hidden sf-label">Departemen</label>
                                <select x-model="row.department_id" class="sf-input text-base">
                                    <option value="">Pilih departemen</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="relative md:col-span-3">
                                <label class="md:hidden sf-label">Bahan Baku</label>
                                <input type="text"
                                       x-model="row.searchQuery"
                                       @input.debounce.300ms="searchItems(index)"
                                       @focus="row.searchResults.length > 0 && (row.showSearch = true)"
                                       @keydown.escape="row.showSearch = false"
                                       class="sf-input text-base"
                                       placeholder="Ketik min. 2 huruf..."
                                       autocomplete="off">

                                <div x-show="row.showSearch"
                                     x-cloak
                                     @click.outside="row.showSearch = false"
                                     class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                    <template x-for="item in row.searchResults" :key="item.id">
                                        <button type="button"
                                                @click="selectItem(index, item)"
                                                class="w-full text-left px-4 py-3 hover:bg-primary-50 border-b border-gray-50 last:border-0 transition-colors">
                                            <p class="text-sm font-semibold text-gray-900" x-text="item.name"></p>
                                            <p class="text-xs text-gray-400 font-mono" x-text="item.sku"></p>
                                        </button>
                                    </template>
                                    <div x-show="row.searching" class="px-4 py-3 text-xs text-gray-400">Mencari...</div>
                                </div>

                                <div x-show="row.item_id && !row.showSearch" x-cloak class="mt-1 flex items-center gap-2">
                                    <span class="text-xs text-primary-700 font-mono" x-text="row.item_sku"></span>
                                    <button type="button" @click="clearItem(row)" class="text-xs font-semibold text-red-600">Ganti</button>
                                </div>
                            </div>

                            <div class="md:col-span-1 rounded-xl border border-gray-100 bg-white px-3 py-2 min-h-11">
                                <p class="text-xs text-gray-400">Inv: <span x-text="row.inventory_unit || '-'"></span></p>
                                <p class="text-xs text-gray-400">Base: <span x-text="row.base_unit || '-'"></span></p>
                                <p class="text-xs text-gray-400">PO: <span x-text="row.purchase_unit || '-'"></span></p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="md:hidden sf-label">Target Stok</label>
                                <div class="grid grid-cols-1 gap-2">
                                    <button type="button"
                                            class="min-h-11 rounded-xl border px-3 py-2 text-left transition-colors"
                                            :class="hasTarget(row, dailyTarget) ? 'sf-choice-selected' : 'sf-choice-unselected'"
                                            @click="toggleTarget(row, dailyTarget)">
                                        <span class="block text-xs font-semibold">Stok Harian</span>
                                        <span class="block text-[11px] mt-0.5">Outlet daily</span>
                                    </button>
                                    <button type="button"
                                            class="min-h-11 rounded-xl border px-3 py-2 text-left transition-colors"
                                            :class="hasTarget(row, warehouseTarget) ? 'sf-choice-selected' : 'sf-choice-unselected'"
                                            @click="toggleTarget(row, warehouseTarget)">
                                        <span class="block text-xs font-semibold">Gudang Utama</span>
                                        <span class="block text-[11px] mt-0.5">Gudang outlet</span>
                                    </button>
                                </div>
                            </div>

                            <div class="md:col-span-3">
                                <div x-show="hasTarget(row, dailyTarget)" class="rounded-xl border border-amber-100 bg-amber-50 p-3"
                                     x-cloak>
                                    <p class="text-xs font-semibold text-amber-800 uppercase mb-2">Stok Harian Outlet</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block mb-1 text-center text-xs text-amber-700">Utuh</label>
                                            <input type="text" inputmode="decimal" x-model="row.qty_whole" class="sf-input text-center text-base" placeholder="0">
                                            <span class="mt-0.5 block truncate text-center text-xs text-gray-500" x-text="row.inventory_unit || 'inv'"></span>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-center text-xs text-amber-700">Ecer</label>
                                            <input type="text" inputmode="decimal" x-model="row.qty_loose" class="sf-input text-center text-base" placeholder="0">
                                            <span class="mt-0.5 block truncate text-center text-xs text-gray-500" x-text="row.base_unit || 'base'"></span>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-center text-xs text-amber-700">Total</label>
                                            <div class="min-h-11 rounded-xl bg-white border border-amber-100 px-2 py-3 text-center text-sm text-gray-700" x-text="getDailyQtyInBase(row)"></div>
                                            <span class="mt-0.5 block truncate text-center text-xs text-gray-500" x-text="row.base_unit || 'base'"></span>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="hasTarget(row, warehouseTarget)" x-cloak class="mt-2 rounded-xl border border-blue-100 bg-blue-50 p-3">
                                    <p class="text-xs font-semibold text-blue-800 uppercase mb-2">Gudang Utama</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div>
                                            <label class="block mb-1 text-xs text-blue-700">Qty pembelian</label>
                                            <input type="text" inputmode="decimal" x-model="row.qty_purchase" class="sf-input text-base" placeholder="0">
                                            <span class="mt-0.5 block truncate text-xs text-gray-500" x-text="row.purchase_unit || 'unit'"></span>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-xs text-blue-700">Total base</label>
                                            <div class="min-h-11 rounded-xl bg-white border border-blue-100 px-3 py-3 text-sm text-gray-700" x-text="getWarehouseQtyInBase(row) + ' ' + (row.base_unit || 'base')"></div>
                                        </div>
                                    </div>
                                </div>

                                <input type="text" x-model="row.notes" class="sf-input text-base mt-2" placeholder="Catatan baris (opsional)">
                            </div>

                            <div class="hidden md:col-span-1 md:flex justify-center pt-1">
                                <button type="button"
                                        @click="removeRow(index)"
                                        class="sf-icon-action sf-icon-danger-soft"
                                        title="Hapus baris"
                                        aria-label="Hapus baris">
                                    <i class="ti ti-x text-sm" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div x-show="row.item_id" x-cloak class="flex justify-end">
                            <button type="button"
                                    @click="row.suggestionOpen = !row.suggestionOpen"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                    :aria-expanded="row.suggestionOpen">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3a6 6 0 00-3.6 10.8c.75.56 1.263 1.315 1.263 2.2h4.674c0-.885.513-1.64 1.263-2.2A6 6 0 0012 3z"/>
                                </svg>
                                <span x-text="row.suggestionOpen ? 'Sembunyikan Saran' : 'Lihat Saran Stok'"></span>
                            </button>
                        </div>

                        <div x-show="row.suggestionOpen" x-cloak class="space-y-2">
                            <div x-show="row.suggestionLoading" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500">
                                Menghitung saran stok...
                            </div>

                            <div x-show="row.suggestion && !row.suggestionLoading"
                                 class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <div class="flex items-center gap-2 text-sm font-semibold text-amber-900">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3a6 6 0 00-3.6 10.8c.75.56 1.263 1.315 1.263 2.2h4.674c0-.885.513-1.64 1.263-2.2A6 6 0 0012 3z"/>
                                    </svg>
                                    Saran Stok
                                </div>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm text-amber-800">
                                    <p>Stok saat ini: <strong x-text="formatQty(row.suggestion.current_qty) + ' ' + row.suggestion.unit_abbreviation"></strong></p>
                                    <template x-if="row.suggestion.has_config">
                                        <p>Rata-rata/hari: <strong x-text="formatQty(row.suggestion.avg_daily_usage) + ' ' + row.suggestion.unit_abbreviation"></strong></p>
                                    </template>
                                    <template x-if="row.suggestion.has_config">
                                        <p>Estimasi habis: <strong x-text="row.suggestion.days_remaining === null ? 'Belum ada pemakaian' : row.suggestion.days_remaining + ' hari'"></strong></p>
                                    </template>
                                    <template x-if="row.suggestion.has_config">
                                        <p>Rekomendasi: <strong x-text="formatQty(row.suggestion.recommended_order) + ' ' + row.suggestion.unit_abbreviation"></strong></p>
                                    </template>
                                </div>
                                <p x-show="row.suggestion.is_below_reorder" class="mt-2 text-sm font-bold text-red-700">
                                    Di bawah reorder point.
                                </p>
                                <template x-if="row.suggestion.has_config && row.suggestion.upcoming_events && row.suggestion.upcoming_events.length > 0">
                                    <div class="mt-2 space-y-1 border-t border-amber-200 pt-2">
                                        <template x-for="event in row.suggestion.upcoming_events" :key="event.id">
                                            <p class="text-xs text-amber-800">
                                                Event: <strong x-text="event.name"></strong>
                                                <span x-text="formatDemandChange(event.demand_change_pct)"></span>
                                                <span x-text="'(dalam ' + event.days_until + ' hari)'"></span>
                                            </p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center">
                <button type="button" @click="addRows(1)" class="sf-btn-secondary">+ Tambah 1 Baris</button>
                <button type="button" @click="addRows(5)" class="sf-btn-secondary">+ Tambah 5 Baris</button>
                <span class="sm:ml-auto text-sm text-gray-500" x-text="validRows.length + ' baris siap disimpan'"></span>
            </div>

            <p x-show="error" x-cloak class="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></p>
        </x-sf.card>
    </div>

    <div class="fixed bottom-0 left-0 right-0 lg:left-64 z-40 bg-white/95 border-t border-gray-100 px-4 pt-3 backdrop-blur-sm"
         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom))">
        <div class="w-full flex items-center gap-3">
            <a href="{{ route('operations.open-stocks.index') }}" class="sf-btn-secondary">Batal</a>
            <div class="flex-1 text-center text-sm text-gray-500">
                <span x-text="validRows.length + ' baris valid'"></span>
                <span x-show="rows.length > validRows.length" x-cloak class="text-amber-600">
                    (<span x-text="rows.length - validRows.length"></span> belum lengkap)
                </span>
            </div>
            <button type="button"
                    @click="submitBatch()"
                    :disabled="submitting || validRows.length === 0"
                    class="sf-btn-primary">
                <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="submitting ? 'Menyimpan...' : 'Simpan Batch Draft'"></span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openStockBatch(config) {
    return {
        storeUrl: config.storeUrl,
        searchUrl: config.searchUrl,
        suggestionUrl: config.suggestionUrl,
        indexUrl: config.indexUrl,
        csrf: config.csrf,
        dailyTarget: config.dailyTarget,
        warehouseTarget: config.warehouseTarget,
        form: {
            outlet_id: config.selectedOutletId || '',
            business_date: config.today,
            stock_target: config.dailyTarget,
            batch_notes: '',
        },
        rows: [],
        submitting: false,
        error: '',
        init() {
            this.addRows(1);
        },
        addRows(count) {
            for (let i = 0; i < count; i++) {
                this.rows.push(this.blankRow());
            }
        },
        blankRow() {
            return {
                id: Date.now() + Math.random(),
                department_id: '',
                targets: [this.dailyTarget],
                item_id: '',
                item_name: '',
                item_sku: '',
                searchQuery: '',
                searchResults: [],
                showSearch: false,
                searching: false,
                inventory_unit: '',
                inventory_unit_id: '',
                base_unit: '',
                base_unit_id: '',
                purchase_unit: '',
                purchase_unit_id: '',
                inventory_ratio: 1,
                purchase_ratio: 1,
                qty_whole: '',
                qty_loose: '',
                qty_purchase: '',
                notes: '',
                suggestion: null,
                suggestionLoading: false,
                suggestionOpen: false,
            };
        },
        removeRow(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) this.addRows(1);
        },
        hasTarget(row, target) {
            return Array.isArray(row.targets) && row.targets.includes(target);
        },
        toggleTarget(row, target) {
            if (!Array.isArray(row.targets)) {
                row.targets = [];
            }

            if (row.targets.includes(target)) {
                if (row.targets.length === 1) {
                    return;
                }

                row.targets = row.targets.filter((selectedTarget) => selectedTarget !== target);
                return;
            }

            row.targets.push(target);
        },
        async searchItems(index) {
            const row = this.rows[index];
            const query = row.searchQuery.trim();
            if (query.length < 2) {
                row.searchResults = [];
                row.showSearch = false;
                return;
            }

            row.searching = true;
            try {
                const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(query)}&outlet_id=${encodeURIComponent(this.form.outlet_id)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                row.searchResults = response.ok ? await response.json() : [];
                row.showSearch = row.searchResults.length > 0;
            } catch (error) {
                row.searchResults = [];
                row.showSearch = false;
            } finally {
                row.searching = false;
            }
        },
        selectItem(index, item) {
            const row = this.rows[index];
            row.item_id = item.id;
            row.item_name = item.name;
            row.item_sku = item.sku;
            row.searchQuery = item.name;
            row.inventory_unit = item.inventory_unit || item.base_unit;
            row.inventory_unit_id = item.inventory_unit_id || item.base_unit_id;
            row.base_unit = item.base_unit;
            row.base_unit_id = item.base_unit_id;
            row.purchase_unit = item.purchase_unit || row.inventory_unit || row.base_unit;
            row.purchase_unit_id = item.purchase_unit_id || row.inventory_unit_id || row.base_unit_id;
            row.inventory_ratio = Number.parseFloat(item.inventory_ratio || 1) || 1;
            row.purchase_ratio = Number.parseFloat(item.purchase_ratio || row.inventory_ratio || 1) || 1;
            row.searchResults = [];
            row.showSearch = false;
            row.suggestionOpen = false;
            this.fetchSuggestion(index);
        },
        clearItem(row) {
            row.item_id = '';
            row.item_name = '';
            row.item_sku = '';
            row.searchQuery = '';
            row.inventory_unit = '';
            row.inventory_unit_id = '';
            row.base_unit = '';
            row.base_unit_id = '';
            row.purchase_unit = '';
            row.purchase_unit_id = '';
            row.inventory_ratio = 1;
            row.purchase_ratio = 1;
            row.suggestion = null;
            row.suggestionLoading = false;
            row.suggestionOpen = false;
        },
        async fetchSuggestion(index) {
            const row = this.rows[index];
            if (!row || !row.item_id || !this.form.outlet_id) return;

            row.suggestionLoading = true;
            try {
                const params = new URLSearchParams({
                    item_id: row.item_id,
                    outlet_id: this.form.outlet_id,
                });
                const response = await fetch(`${this.suggestionUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                row.suggestion = response.ok ? await response.json() : null;
            } catch (error) {
                row.suggestion = null;
            } finally {
                row.suggestionLoading = false;
            }
        },
        refreshSuggestions() {
            this.rows.forEach((row, index) => {
                if (row.item_id) this.fetchSuggestion(index);
            });
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
        parseQty(value) {
            const normalized = String(value || '0').replace(',', '.');
            const parsed = Number.parseFloat(normalized);
            return Number.isFinite(parsed) ? parsed : 0;
        },
        getDailyQtyInBase(row) {
            return ((this.parseQty(row.qty_whole) * row.inventory_ratio) + this.parseQty(row.qty_loose)).toFixed(4);
        },
        getWarehouseQtyInBase(row) {
            return (this.parseQty(row.qty_purchase) * row.purchase_ratio).toFixed(4);
        },
        get validRows() {
            return this.rows.filter((row) => row.item_id && row.department_id && Array.isArray(row.targets) && row.targets.length > 0);
        },
        async submitBatch() {
            this.error = '';
            if (this.validRows.length === 0) {
                this.error = 'Minimal 1 baris dengan departemen dan bahan baku harus diisi.';
                return;
            }

            this.submitting = true;
            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({
                        outlet_id: this.form.outlet_id,
                        business_date: this.form.business_date,
                        stock_target: this.form.stock_target,
                        batch_notes: this.form.batch_notes,
                        items: this.validRows.map((row) => ({
                            department_id: row.department_id,
                            item_id: row.item_id,
                            targets: row.targets,
                            qty_whole: row.qty_whole || '0',
                            qty_loose: row.qty_loose || '0',
                            qty_purchase: row.qty_purchase || '0',
                            cost_per_unit: '',
                            notes: row.notes || '',
                        })),
                    }),
                });
                const payload = await response.json();

                if (!response.ok || !payload.success) {
                    this.error = payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Open Stock gagal disimpan.';
                    this.submitting = false;
                    return;
                }

                window.location.href = payload.redirect || this.indexUrl;
            } catch (error) {
                this.error = 'Terjadi kesalahan koneksi.';
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
