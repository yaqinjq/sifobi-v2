@extends('layouts.app')

@section('title', 'Catat Spoil & Waste')

@section('content')
<x-sf.page-header title="Catat Spoil & Waste" subtitle="Stok langsung berkurang setelah disimpan" back="{{ route('operations.spoil-wastes.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('operations.spoil-wastes.store') }}"
          enctype="multipart/form-data"
          x-data="spoilForm()"
          class="space-y-4">
        @csrf

        <x-sf.card title="Item & Qty">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="sf-label">Outlet</label>
                    <select name="outlet_id" class="sf-input text-base min-h-11" required>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', auth()->user()->outlet_id) === (string) $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Tanggal</label>
                    <input type="date" name="recorded_date" value="{{ old('recorded_date', now()->toDateString()) }}" class="sf-input text-base min-h-11" required>
                </div>
                <div>
                    <label class="sf-label">Departemen *</label>
                    <select name="department_id" class="sf-input text-base min-h-11" required>
                        <option value="">Pilih departemen</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('department_id', auth()->user()->department_id) === (string) $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Jumlah Terbuang *</label>
                    <input type="text" name="qty" x-model="qty" @input="calcBase()" inputmode="decimal" class="sf-input text-base min-h-11" placeholder="0" required>
                </div>
            </div>

            <div class="relative mt-4">
                <label class="sf-label">Item/Bahan Baku *</label>
                <input type="hidden" name="item_id" :value="selectedItem?.id || ''">
                <input type="hidden" name="unit_id" :value="selectedItem?.inventory_unit_id || selectedItem?.base_unit_id || ''">
                <input type="text"
                       x-model="searchQuery"
                       @input.debounce.300ms="searchItems()"
                       @focus="searchItems()"
                       @keydown.escape="showSearch = false"
                       class="sf-input text-base min-h-11"
                       placeholder="Ketik nama atau SKU item"
                       autocomplete="off"
                       required>
                <div x-show="showSearch" x-cloak @click.outside="showSearch = false" class="absolute z-40 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden">
                    <template x-for="item in searchResults" :key="item.id">
                        <button type="button" @click="selectItem(item)" class="w-full text-left px-4 py-3 hover:bg-primary-50 border-b border-gray-100 last:border-0">
                            <span class="block text-sm font-semibold text-gray-900" x-text="item.name"></span>
                            <span class="block text-xs text-gray-500" x-text="`${item.sku} | Stok: ${item.qty_on_hand} ${item.base_unit}`"></span>
                        </button>
                    </template>
                    <div x-show="searchResults.length === 0" class="px-4 py-3 text-sm text-gray-500">Item tidak ditemukan.</div>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Satuan input</span>
                    <span class="font-semibold text-gray-900" x-text="selectedItem?.inventory_unit || '-'"></span>
                </div>
                <div class="flex justify-between gap-3 mt-1">
                    <span class="text-gray-500">Total base unit</span>
                    <span class="font-semibold text-gray-900" x-text="`${qtyInBase} ${selectedItem?.base_unit || ''}`"></span>
                </div>
            </div>
        </x-sf.card>

        <x-sf.card title="Alasan & Bukti">
            <div class="space-y-4">
                <div>
                    <label class="sf-label">Kategori Alasan *</label>
                    <select name="reason_category" class="sf-input text-base min-h-11" required>
                        @foreach($reasonOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('reason_category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Keterangan Detail</label>
                    <textarea name="reason_detail" rows="3" class="sf-input text-base" placeholder="Jelaskan jika perlu">{{ old('reason_detail') }}</textarea>
                </div>
                <div>
                    <label class="sf-label">Foto Bukti</label>
                    <div class="rounded-xl border-2 border-dashed border-gray-200 p-6 text-center cursor-pointer" @click="$refs.photoInput.click()">
                        <div x-show="!photoPreview">
                            <p class="font-semibold text-gray-900">Tap untuk foto / pilih file</p>
                            <p class="text-xs text-gray-400 mt-1">JPG/PNG/WebP, maks 5MB</p>
                        </div>
                        <div x-show="photoPreview" x-cloak>
                            <img :src="photoPreview" alt="Preview foto" class="max-h-48 mx-auto rounded-xl object-cover">
                            <button type="button" @click.stop="clearPhoto()" class="text-sm text-red-600 mt-3">Hapus foto</button>
                        </div>
                    </div>
                    <input type="file" x-ref="photoInput" name="photo" accept="image/*" capture="environment" @change="previewPhoto($event)" class="sr-only">
                </div>
            </div>
        </x-sf.card>

        <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                <a href="{{ route('operations.spoil-wastes.index') }}" class="sf-btn-secondary min-h-11 px-4 text-center">Batal</a>
                <button type="submit" class="sf-btn-primary min-h-11 px-4">Catat Spoil</button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function spoilForm() {
    return {
        selectedItem: null,
        searchQuery: '',
        searchResults: [],
        showSearch: false,
        qty: '',
        qtyInBase: '0.0000',
        photoPreview: null,
        async searchItems() {
            const q = this.searchQuery.trim();
            if (q.length < 2) {
                this.searchResults = [];
                this.showSearch = false;
                return;
            }

            const url = new URL(@js(route('operations.spoil-wastes.search-items')), window.location.origin);
            url.searchParams.set('q', q);

            const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
            this.searchResults = await response.json();
            this.showSearch = true;
        },
        selectItem(item) {
            this.selectedItem = item;
            this.searchQuery = item.name;
            this.showSearch = false;
            this.calcBase();
        },
        calcBase() {
            const qty = Number.parseFloat(String(this.qty || '0').replace(',', '.')) || 0;
            const ratio = Number.parseFloat(this.selectedItem?.inventory_ratio || 1) || 1;
            this.qtyInBase = (qty * ratio).toFixed(4);
        },
        previewPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => this.photoPreview = e.target.result;
            reader.readAsDataURL(file);
        },
        clearPhoto() {
            this.photoPreview = null;
            this.$refs.photoInput.value = '';
        },
    };
}
</script>
@endpush
@endsection
