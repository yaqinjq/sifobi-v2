@extends('layouts.app')

@section('title', $item->name)

@section('content')
<x-sf.page-header
    title="{{ $item->name }}"
    subtitle="SKU: {{ $item->canonical_sku }}"
    back="{{ route('master-data.items.index') }}"
>
    <x-slot:actions>
        @can('manage_items')
            <a href="{{ route('master-data.items.edit', $item) }}" class="sf-btn-primary text-xs px-3 py-2 min-h-11">Edit</a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

@php
    $typeBadge = match($item->item_type) {
        'WIP_L1' => 'badge-wip-l1',
        'WIP_L2' => 'badge-wip-l2',
        'WIP_L3' => 'badge-wip-l3',
        'PACKAGING' => 'badge-packaging',
        'MENU_ITEM' => 'badge-menu-item',
        default => 'badge-active',
    };
    $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
    $primaryOutletId = $item->outlets->first()?->id;
@endphp

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full"
     x-data="itemConversions({
        storeUrl: @js(route('master-data.items.conversions.store', $item)),
        conversions: @js($conversions),
     })">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="space-y-5">
            <x-sf.card>
                <div class="aspect-square rounded-2xl overflow-hidden bg-gray-100 flex items-center justify-center">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="text-center px-4">
                            <div class="text-sm font-bold text-gray-500 mb-2">FOTO</div>
                            <p class="text-sm font-semibold text-gray-700">Belum ada foto</p>
                        </div>
                    @endif
                </div>
                <div class="mt-4 space-y-3">
                    <span class="badge-active break-all">{{ $item->canonical_sku }}</span>
                    @can('manage_items')
                        <a href="{{ route('master-data.items.edit', $item) }}" class="sf-btn-secondary w-full">Upload Foto</a>
                    @endcan
                </div>
            </x-sf.card>

            <x-sf.card title="Satuan & Konversi">
                <div class="space-y-3">
                    <div class="rounded-2xl border sf-unit-blue p-4">
                        <p class="text-xs font-bold uppercase tracking-wide">Dasar</p>
                        <p class="font-heading font-bold text-lg mt-1">{{ $item->baseUnit?->code ?? '-' }}</p>
                    </div>
                    <div class="rounded-2xl border sf-unit-orange p-4">
                        <p class="text-xs font-bold uppercase tracking-wide">Inventory</p>
                        <p class="font-heading font-bold text-lg mt-1">{{ $item->inventoryUnit?->code ?? '-' }}</p>
                        <p class="text-sm mt-1">1 {{ $item->inventoryUnit?->abbreviation ?? $item->inventoryUnit?->code ?? 'unit' }} = {{ rtrim(rtrim((string) ($item->inventory_ratio ?? '1'), '0'), '.') }} {{ $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? 'base' }}</p>
                    </div>
                    <div class="rounded-2xl border sf-unit-green p-4">
                        <p class="text-xs font-bold uppercase tracking-wide">Pembelian</p>
                        <p class="font-heading font-bold text-lg mt-1">{{ $item->purchaseUnit?->code ?? '-' }}</p>
                        <p class="text-sm mt-1">
                            @if($item->purchaseUnit && $item->purchase_ratio)
                                1 {{ $item->purchaseUnit->abbreviation ?? $item->purchaseUnit->code }} = {{ rtrim(rtrim((string) $item->purchase_ratio, '0'), '.') }} {{ $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? 'base' }}
                            @else
                                Belum diatur
                            @endif
                        </p>
                    </div>
                </div>
            </x-sf.card>
        </div>

        <div class="lg:col-span-2 space-y-5">
            <x-sf.card title="Identitas">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $typeBadge }}">{{ $item->item_type }}</span>
                    @if($item->jenis)
                        <span class="{{ $item->jenis->badgeClass() }}">{{ $item->jenis->name }}</span>
                    @endif
                    @if($item->category)
                        <span class="badge-draft">{{ $item->category->name }}</span>
                    @endif
                    <span class="{{ $item->is_active ? 'badge-active' : 'badge-inactive' }}">
                        {{ $item->is_active ? 'AKTIF' : 'NON-AKTIF' }}
                    </span>
                </div>

                <h2 class="font-heading font-bold text-2xl text-gray-900 mt-4">{{ $item->name }}</h2>
                @if($item->keterangan_pembeda)
                    <p class="italic text-gray-500 mt-1">{{ $item->keterangan_pembeda }}</p>
                @endif
                @if($item->description)
                    <p class="text-sm text-gray-600 mt-3">{{ $item->description }}</p>
                @endif

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                        <p class="text-xs text-gray-500">Dept. Utama</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $item->primaryDepartment?->name ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                        <p class="text-xs text-gray-500">Opname</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $item->opname_frequency ?? 'DAILY' }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                        <p class="text-xs text-gray-500">Yield</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $item->yield_pct ? rtrim(rtrim((string) $item->yield_pct, '0'), '.').'%' : '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                        <p class="text-xs text-gray-500">Harga Terakhir</p>
                        <p class="font-semibold text-gray-900 mt-1">Rp {{ number_format((float) ($item->last_purchase_price ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="sf-label">Departemen Pemakai</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse($item->departments as $department)
                            <span class="badge-active">{{ $department->name }}</span>
                        @empty
                            <span class="text-sm text-gray-400">Belum ada departemen pemakai.</span>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5">
                    <p class="sf-label">Batch Tracking</p>
                    <span class="{{ $item->track_expiry ? 'badge-approved' : 'badge-inactive' }}">
                        {{ $item->track_expiry ? 'Aktif' : 'Non-aktif' }}
                    </span>
                </div>
            </x-sf.card>

            <x-sf.card title="Kode SKU per Brand (Finance Code)">
                <div class="mb-4 rounded-2xl border border-primary-100 bg-primary-50 px-4 py-3">
                    <p class="text-sm font-semibold text-primary-900">Canonical SKU: {{ $item->canonical_sku }}</p>
                    <p class="text-sm text-primary-800 mt-1">
                        Canonical SKU adalah kode global sistem. Kode Finance per brand dicatat di sini agar kode lama My Kopi-O, Quali, atau brand lain tetap bisa dipakai tanpa membuat item duplikat.
                    </p>
                </div>
                <div x-data="itemAliasEditor({
                        storeUrl: @js(route('master-data.items.aliases.store', $item)),
                        aliases: @js($aliases),
                        brands: @js($brands->map(fn ($brand) => ['id' => (int) $brand->id, 'name' => $brand->name])->values()),
                    })"
                    class="space-y-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[620px]">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100">
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Brand</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode Finance</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama Brand</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Primary</th>
                                    <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="alias in aliases" :key="alias.id">
                                    <tr class="odd:bg-white even:bg-gray-50/60">
                                        <td class="px-3 py-3 font-semibold text-gray-900" x-text="alias.brand_name"></td>
                                        <td class="px-3 py-3 text-gray-800" x-text="alias.brand_sku"></td>
                                        <td class="px-3 py-3 text-gray-600" x-text="alias.brand_item_name || '-'"></td>
                                        <td class="px-3 py-3">
                                            <span x-show="alias.is_primary" class="badge-active">YA</span>
                                            <span x-show="!alias.is_primary" class="badge-draft">-</span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            @can('manage_items')
                                                <button type="button" class="sf-btn-danger text-xs px-3 py-1.5 min-h-11" @click="destroy(alias)">Hapus</button>
                                            @endcan
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="aliases.length === 0">
                                    <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada kode SKU per brand.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @can('manage_items')
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_1fr_1.4fr_auto_auto] gap-2 items-end">
                            <x-sf.form-group label="Brand" for="alias_brand_id">
                                <select id="alias_brand_id" x-model="form.brand_id" class="sf-input text-base">
                                    <option value="">Pilih brand</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </x-sf.form-group>
                            <x-sf.form-group label="Kode Finance" for="alias_brand_sku">
                                <input id="alias_brand_sku" x-model="form.brand_sku" class="sf-input text-base uppercase" maxlength="100">
                            </x-sf.form-group>
                            <x-sf.form-group label="Nama Brand" for="alias_brand_item_name">
                                <input id="alias_brand_item_name" x-model="form.brand_item_name" class="sf-input text-base" maxlength="255">
                            </x-sf.form-group>
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" x-model="form.is_primary" class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                                Primary
                            </label>
                            <button type="button" class="sf-btn-primary" :disabled="loading" @click="store()">
                                <span x-text="loading ? 'Menyimpan...' : '+ Tambah'"></span>
                            </button>
                        </div>
                        <p x-show="error" x-cloak class="text-sm text-red-600" x-text="error"></p>
                    @endcan
                </div>
            </x-sf.card>

            <x-sf.card title="Konversi Satuan Tambahan">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[520px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Dari</th>
                                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">=</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Jumlah</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Ke</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="conversion in conversions" :key="conversion.id">
                                <tr class="odd:bg-white even:bg-gray-50/60">
                                    <td class="px-3 py-3 font-semibold text-gray-900" x-text="conversion.from_unit"></td>
                                    <td class="px-3 py-3 text-center text-gray-400">=</td>
                                    <td class="px-3 py-3 text-right text-gray-800" x-text="conversion.factor"></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900" x-text="conversion.to_unit"></td>
                                    <td class="px-3 py-3 text-right">
                                        @can('manage_items')
                                            <button type="button" class="sf-btn-danger text-xs px-3 py-1.5 min-h-11" @click="destroy(conversion)">Hapus</button>
                                        @endcan
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="conversions.length === 0">
                                <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada konversi tambahan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @can('manage_items')
                    <form class="mt-4 grid grid-cols-1 md:grid-cols-[1fr_auto_1fr_1fr_auto] gap-2 items-end" @submit.prevent="store()">
                        <x-sf.form-group label="Dari" for="from_unit_id">
                            <select id="from_unit_id" x-model="form.from_unit_id" class="sf-input text-base" required>
                                <option value="">Pilih</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </x-sf.form-group>
                        <div class="hidden md:flex items-center justify-center min-h-11 pb-1 text-gray-400 font-semibold">=</div>
                        <x-sf.form-group label="Jumlah" for="factor">
                            <input id="factor" type="text" inputmode="decimal" x-model="form.factor" class="sf-input text-base" required>
                        </x-sf.form-group>
                        <x-sf.form-group label="Ke" for="to_unit_id">
                            <select id="to_unit_id" x-model="form.to_unit_id" class="sf-input text-base" required>
                                <option value="">Pilih</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </x-sf.form-group>
                        <button type="submit" class="sf-btn-primary" :disabled="loading">
                            <span x-text="loading ? 'Menyimpan...' : '+ Tambah'"></span>
                        </button>
                    </form>
                    <p x-show="error" x-cloak class="mt-3 text-sm text-red-600" x-text="error"></p>
                @endcan
            </x-sf.card>

            <x-sf.card title="Distribusi Outlet">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($outlets as $outlet)
                        @php $isSelected = $item->outlets->contains('id', $outlet->id); @endphp
                        <div class="min-h-16 rounded-xl border px-3 py-3 {{ $isSelected ? 'sf-choice-selected' : 'sf-choice-unselected' }}">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm">{{ $outlet->name }}</span>
                                @if($isSelected)
                                    <span class="text-primary-700 text-sm font-bold">OK</span>
                                @endif
                            </div>
                            @if($primaryOutletId === $outlet->id)
                                <span class="badge-active mt-2">Utama</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-sf.card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function itemConversions(config) {
        return {
            storeUrl: config.storeUrl,
            conversions: config.conversions || [],
            loading: false,
            error: '',
            form: {
                from_unit_id: '',
                to_unit_id: '',
                factor: '',
            },
            async store() {
                this.loading = true;
                this.error = '';

                try {
                    const response = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        this.error = payload.message || 'Konversi gagal disimpan.';
                        return;
                    }

                    const index = this.conversions.findIndex((conversion) => conversion.id === payload.conversion.id);

                    if (index >= 0) {
                        this.conversions[index] = payload.conversion;
                    } else {
                        this.conversions.push(payload.conversion);
                    }

                    this.form = { from_unit_id: '', to_unit_id: '', factor: '' };
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                } finally {
                    this.loading = false;
                }
            },
            async destroy(conversion) {
                this.error = '';

                try {
                    const response = await fetch(conversion.destroy_url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });

                    if (!response.ok) {
                        const payload = await response.json();
                        this.error = payload.message || 'Konversi gagal dihapus.';
                        return;
                    }

                    this.conversions = this.conversions.filter((item) => item.id !== conversion.id);
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                }
            },
        };
    }

    function itemAliasEditor(config) {
        return {
            storeUrl: config.storeUrl,
            aliases: config.aliases || [],
            brands: config.brands || [],
            loading: false,
            error: '',
            form: {
                brand_id: '',
                brand_sku: '',
                brand_item_name: '',
                is_primary: false,
            },
            async store() {
                this.loading = true;
                this.error = '';

                try {
                    const response = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        this.error = payload.message || 'Alias brand gagal disimpan.';
                        return;
                    }

                    if (payload.alias.is_primary) {
                        this.aliases = this.aliases.map((alias) => {
                            if (alias.brand_id === payload.alias.brand_id) {
                                return { ...alias, is_primary: false };
                            }

                            return alias;
                        });
                    }

                    this.aliases.push(payload.alias);
                    this.form = { brand_id: '', brand_sku: '', brand_item_name: '', is_primary: false };
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                } finally {
                    this.loading = false;
                }
            },
            async destroy(alias) {
                this.error = '';

                try {
                    const response = await fetch(alias.destroy_url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });

                    if (!response.ok) {
                        const payload = await response.json();
                        this.error = payload.message || 'Alias brand gagal dihapus.';
                        return;
                    }

                    this.aliases = this.aliases.filter((item) => item.id !== alias.id);
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                }
            },
        };
    }
</script>
@endpush
