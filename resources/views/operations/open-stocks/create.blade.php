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
    <div class="px-4 pt-4 lg:px-6 max-w-5xl mx-auto space-y-4">
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
                    <select id="outlet_id" x-model="form.outlet_id" class="sf-input text-base" required>
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
            <div class="hidden lg:grid grid-cols-[1fr_1.7fr_1fr_1.1fr_1.5fr_auto] gap-3 px-1 pb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <span>Departemen</span>
                <span>Bahan Baku</span>
                <span>Satuan</span>
                <span>Target</span>
                <span>Qty</span>
                <span class="text-right">Aksi</span>
            </div>

            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="row.id">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2 lg:hidden">
                            <span class="text-xs font-bold text-gray-400" x-text="'Baris ' + (index + 1)"></span>
                            <button type="button"
                                    @click="removeRow(index)"
                                    class="sf-icon-action sf-icon-danger-soft"
                                    title="Hapus baris"
                                    aria-label="Hapus baris">
                                x
                            </button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.7fr_1fr_1.1fr_1.5fr_auto] gap-3 items-start">
                            <div>
                                <label class="lg:hidden sf-label">Departemen</label>
                                <select x-model="row.department_id" class="sf-input text-base">
                                    <option value="">Pilih departemen</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="relative">
                                <label class="lg:hidden sf-label">Bahan Baku</label>
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

                            <div class="rounded-xl border border-gray-100 bg-white px-3 py-2 min-h-11">
                                <p class="text-xs text-gray-400">Inv: <span x-text="row.inventory_unit || '-'"></span></p>
                                <p class="text-xs text-gray-400">Base: <span x-text="row.base_unit || '-'"></span></p>
                                <p class="text-xs text-gray-400">PO: <span x-text="row.purchase_unit || '-'"></span></p>
                            </div>

                            <div>
                                <label class="lg:hidden sf-label">Target Stok</label>
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

                            <div>
                                <div x-show="hasTarget(row, dailyTarget)" class="rounded-xl border border-amber-100 bg-amber-50 p-3"
                                     x-cloak>
                                    <p class="text-xs font-semibold text-amber-800 uppercase mb-2">Stok Harian Outlet</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text" inputmode="decimal" x-model="row.qty_whole" class="sf-input text-base" placeholder="Utuh">
                                            <span class="text-xs text-gray-500 w-12 truncate" x-text="row.inventory_unit || 'inv'"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="text" inputmode="decimal" x-model="row.qty_loose" class="sf-input text-base" placeholder="Ecer">
                                            <span class="text-xs text-gray-500 w-12 truncate" x-text="row.base_unit || 'base'"></span>
                                        </div>
                                        <div class="rounded-xl bg-white border border-amber-100 px-3 py-3 text-sm text-gray-700" x-text="getDailyQtyInBase(row) + ' ' + (row.base_unit || 'base')"></div>
                                    </div>
                                </div>

                                <div x-show="hasTarget(row, warehouseTarget)" x-cloak class="rounded-xl border border-blue-100 bg-blue-50 p-3"
                                     :class="hasTarget(row, dailyTarget) ? 'mt-2' : ''">
                                    <p class="text-xs font-semibold text-blue-800 uppercase mb-2">Gudang Utama</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text" inputmode="decimal" x-model="row.qty_purchase" class="sf-input text-base" placeholder="Qty pembelian">
                                            <span class="text-xs text-gray-500 w-14 truncate" x-text="row.purchase_unit || 'unit'"></span>
                                        </div>
                                        <div class="rounded-xl bg-white border border-blue-100 px-3 py-3 text-sm text-gray-700" x-text="getWarehouseQtyInBase(row) + ' ' + (row.base_unit || 'base')"></div>
                                    </div>
                                </div>

                                <input type="text" x-model="row.notes" class="sf-input text-base mt-2" placeholder="Catatan baris (opsional)">
                            </div>

                            <div class="hidden lg:flex justify-end">
                                <button type="button"
                                        @click="removeRow(index)"
                                        class="sf-icon-action sf-icon-danger-soft"
                                        title="Hapus baris"
                                        aria-label="Hapus baris">
                                    x
                                </button>
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
        <div class="max-w-5xl mx-auto flex items-center gap-3">
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
