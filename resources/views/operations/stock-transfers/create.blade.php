@extends('layouts.app')

@section('title', 'Buat Transfer Stok')

@section('content')
<x-sf.page-header title="Buat Transfer Stok" subtitle="Pindahkan stok antar outlet" back="{{ route('operations.stock-transfers.index') }}" />

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full"
     x-data="transferForm()">

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('operations.stock-transfers.store') }}" @submit.prevent="submitForm">
        @csrf

        <x-sf.card title="Detail Transfer" class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-sf.form-group label="Outlet Asal" for="from_outlet_id" :required="true">
                    <select name="from_outlet_id" id="from_outlet_id" class="sf-input" required
                            x-model="fromOutletId">
                        <option value="">Pilih outlet asal...</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected(old('from_outlet_id') == $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </x-sf.form-group>

                <x-sf.form-group label="Outlet Tujuan" for="to_outlet_id" :required="true">
                    <select name="to_outlet_id" id="to_outlet_id" class="sf-input" required>
                        <option value="">Pilih outlet tujuan...</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}"
                                    :disabled="fromOutletId == {{ $outlet->id }}"
                                    @selected(old('to_outlet_id') == $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </x-sf.form-group>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <x-sf.form-group label="Tanggal Transfer" for="transfer_date" :required="true">
                    <input type="date"
                           name="transfer_date"
                           id="transfer_date"
                           value="{{ old('transfer_date', date('Y-m-d')) }}"
                           class="sf-input"
                           required>
                </x-sf.form-group>

                <x-sf.form-group label="Catatan" for="notes">
                    <input type="text"
                           name="notes"
                           id="notes"
                           value="{{ old('notes') }}"
                           class="sf-input"
                           placeholder="Keterangan transfer (opsional)"
                           maxlength="2000">
                </x-sf.form-group>
            </div>
        </x-sf.card>

        <x-sf.card title="Daftar Item" class="mb-4">
            {{-- Search item --}}
            <div class="mb-4">
                <div class="relative">
                    <input type="text"
                           x-model="itemSearch"
                           @input.debounce.400ms="searchItems()"
                           placeholder="Cari item untuk ditambahkan..."
                           class="sf-input pl-9">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Dropdown hasil search --}}
                <div x-show="searchResults.length > 0"
                     x-transition
                     class="mt-1 rounded-xl border border-gray-200 bg-white shadow-lg divide-y divide-gray-50 max-h-52 overflow-y-auto z-10 relative">
                    <template x-for="result in searchResults" :key="result.id">
                        <button type="button"
                                @click="addItem(result)"
                                class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm">
                            <span class="font-medium text-gray-800" x-text="result.name"></span>
                            <span class="text-gray-400 ml-2 text-xs" x-text="result.unit"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Items list --}}
            <div class="space-y-3">
                <template x-if="items.length === 0">
                    <p class="text-sm text-gray-400 text-center py-4">Belum ada item yang dipilih</p>
                </template>

                <template x-for="(item, idx) in items" :key="item.item_id">
                    <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800" x-text="item.name"></p>
                            <p class="text-xs text-gray-500" x-text="item.unit"></p>
                            <input type="hidden" :name="`items[${idx}][item_id]`" :value="item.item_id">
                        </div>
                        <div class="shrink-0">
                            <input type="number"
                                   :name="`items[${idx}][qty]`"
                                   x-model="item.qty"
                                   min="0.000001"
                                   step="0.001"
                                   required
                                   placeholder="0"
                                   class="sf-input w-28 text-right text-sm">
                        </div>
                        <button type="button" @click="removeItem(idx)"
                                class="shrink-0 text-gray-400 hover:text-red-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </x-sf.card>

        <div class="sticky bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:static bg-white/95 backdrop-blur border-t border-gray-100 -mx-4 px-4 py-3 lg:border-0 lg:bg-transparent lg:backdrop-blur-none lg:mx-0 lg:px-0 lg:py-0">
            <button type="submit"
                    :disabled="items.length === 0"
                    class="sf-btn-primary w-full sm:w-auto min-h-11"
                    :class="items.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                Simpan sebagai Draft
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function transferForm() {
    return {
        fromOutletId: '{{ old('from_outlet_id') }}',
        itemSearch: '',
        searchResults: [],
        items: [],

        async searchItems() {
            if (this.itemSearch.length < 2) { this.searchResults = []; return; }
            try {
                const res = await fetch(`/operations/open-stocks/item-search?q=${encodeURIComponent(this.itemSearch)}`);
                const data = await res.json();
                this.searchResults = (data.items || []).filter(r => !this.items.find(i => i.item_id === r.id));
            } catch { this.searchResults = []; }
        },

        addItem(result) {
            this.items.push({ item_id: result.id, name: result.name, unit: result.unit || result.inventory_unit || '', qty: '' });
            this.itemSearch = '';
            this.searchResults = [];
        },

        removeItem(idx) {
            this.items.splice(idx, 1);
        },

        submitForm(e) {
            e.target.submit();
        }
    };
}
</script>
@endpush
@endsection
