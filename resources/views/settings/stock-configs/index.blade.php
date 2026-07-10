@extends('layouts.app')

@section('title', 'Konfigurasi Stok')

@section('content')
<x-sf.page-header
    title="Konfigurasi Stok"
    subtitle="Min, max, dan reorder point per item dan outlet"
    back="{{ auth()->user()->can('manage_settings') ? route('settings.index') : route('dashboard') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto_auto] gap-3 items-end">
        <x-sf.form-group label="Outlet" for="filter_outlet">
            <select id="filter_outlet" name="outlet_id" class="sf-input text-base">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) $outletId === (string) $outlet->id)>
                        {{ $outlet->name }}
                    </option>
                @endforeach
            </select>
        </x-sf.form-group>
        <x-sf.form-group label="Cari Item" for="filter_item">
            <input id="filter_item" name="q" value="{{ $search }}" class="sf-input text-base" placeholder="Nama atau SKU">
        </x-sf.form-group>
        <button type="submit" class="sf-btn-primary">Filter</button>
        <a href="{{ route('settings.stock-configs.index') }}" class="sf-btn-secondary">Reset</a>
    </form>

    <x-sf.card title="Daftar Konfigurasi">
        <div class="hidden lg:grid grid-cols-[1.6fr_1.2fr_repeat(3,0.8fr)_0.8fr_auto] gap-3 px-3 py-2 border-b border-gray-100 text-xs font-semibold uppercase text-gray-500">
            <span>Item</span>
            <span>Outlet</span>
            <span>Min</span>
            <span>Max</span>
            <span>Reorder</span>
            <span>Satuan</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($configs as $config)
                <div class="py-4" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.stock-configs.update', $config) }}"
                          class="grid grid-cols-1 lg:grid-cols-[1.6fr_1.2fr_repeat(3,0.8fr)_0.8fr_auto] gap-3 items-center">
                        @csrf
                        @method('PUT')

                        <div>
                            <span class="lg:hidden sf-label">Item</span>
                            <div x-show="!editing">
                                <p class="font-semibold text-gray-900">{{ $config->item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $config->item->canonical_sku }}</p>
                            </div>
                            <select x-show="editing" x-cloak name="item_id" class="sf-input text-base" required>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" @selected($config->item_id === $item->id)>
                                        {{ $item->name }} ({{ $item->canonical_sku }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Outlet</span>
                            <span x-show="!editing" class="text-sm text-gray-700">{{ $config->outlet->name }}</span>
                            <select x-show="editing" x-cloak name="outlet_id" class="sf-input text-base" required>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" @selected($config->outlet_id === $outlet->id)>
                                        {{ $outlet->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @foreach([
                            'min_stock_qty' => 'Min Stok',
                            'max_stock_qty' => 'Max Stok',
                            'reorder_point' => 'Reorder Point',
                        ] as $field => $label)
                            <div>
                                <span class="lg:hidden sf-label">{{ $label }}</span>
                                <span x-show="!editing" class="text-sm font-semibold text-gray-800">
                                    {{ number_format((float) $config->{$field}, 2, ',', '.') }}
                                </span>
                                <input x-show="editing" x-cloak type="number" step="0.0001" min="0"
                                       name="{{ $field }}" value="{{ $config->{$field} }}"
                                       class="sf-input text-base" required>
                            </div>
                        @endforeach

                        <div>
                            <span class="lg:hidden sf-label">Satuan</span>
                            <span x-show="!editing" class="text-sm text-gray-700">
                                {{ $config->unit?->abbreviation ?? $config->item->baseUnit?->abbreviation ?? 'Base' }}
                            </span>
                            <select x-show="editing" x-cloak name="unit_id" class="sf-input text-base">
                                <option value="">Satuan dasar item</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" @selected($config->unit_id === $unit->id)>
                                        {{ $unit->name }} ({{ $unit->abbreviation }})
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="avg_daily_usage_days" value="{{ $config->avg_daily_usage_days }}">
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" x-show="!editing" @click="editing = true" class="sf-btn-secondary text-xs">Edit</button>
                            <button type="submit" x-show="editing" x-cloak class="sf-btn-primary text-xs">Simpan</button>
                            <button type="button" x-show="editing" x-cloak @click="editing = false" class="sf-btn-secondary text-xs">Batal</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.stock-configs.destroy', $config) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sf-btn-danger text-xs">Hapus</button>
                    </form>
                </div>
            @empty
                <div class="py-10 text-center text-sm text-gray-500">Belum ada konfigurasi stok.</div>
            @endforelse
        </div>

        <div class="pt-4">{{ $configs->links() }}</div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Konfigurasi">
        <form method="POST" action="{{ route('settings.stock-configs.store') }}"
              class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
            @csrf
            <x-sf.form-group label="Item" for="item_id" :required="true">
                <select id="item_id" name="item_id" class="sf-input text-base" required>
                    <option value="">Pilih item</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                            {{ $item->name }} ({{ $item->canonical_sku }})
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Outlet" for="outlet_id" :required="true">
                <select id="outlet_id" name="outlet_id" class="sf-input text-base" required>
                    <option value="">Pilih outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected(old('outlet_id') == $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Min Stok" for="min_stock_qty" :required="true">
                <input id="min_stock_qty" name="min_stock_qty" type="number" step="0.0001" min="0"
                       value="{{ old('min_stock_qty', 0) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <x-sf.form-group label="Max Stok" for="max_stock_qty" :required="true">
                <input id="max_stock_qty" name="max_stock_qty" type="number" step="0.0001" min="0"
                       value="{{ old('max_stock_qty', 0) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <x-sf.form-group label="Reorder Point" for="reorder_point" :required="true">
                <input id="reorder_point" name="reorder_point" type="number" step="0.0001" min="0"
                       value="{{ old('reorder_point', 0) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <x-sf.form-group label="Satuan" for="unit_id">
                <select id="unit_id" name="unit_id" class="sf-input text-base">
                    <option value="">Satuan dasar item</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>
                            {{ $unit->name }} ({{ $unit->abbreviation }})
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Periode Rata-rata" for="avg_daily_usage_days" hint="Jumlah hari histori pemakaian">
                <input id="avg_daily_usage_days" name="avg_daily_usage_days" type="number" min="1" max="365"
                       value="{{ old('avg_daily_usage_days', 7) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <button type="submit" class="sf-btn-primary w-full">Simpan</button>
        </form>
    </x-sf.card>
</div>
@endsection
