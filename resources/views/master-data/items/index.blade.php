@extends('layouts.app')

@section('title', 'Master Item')

@section('content')
<x-sf.page-header title="Master Item" subtitle="Bahan Baku & Produk">
    <x-slot:actions>
        @can('manage_items')
            <a href="{{ route('master-data.items.create') }}"
               class="sf-btn-primary text-xs px-3 py-2 min-h-11">
                + Tambah Item
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-4 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full"
     x-data="{
        search: @js(request('q', '')),
        type: @js(request('type', '')),
        status: @js(request('status', '')),
        matches(text, itemType, itemStatus) {
            const term = this.search.toLowerCase();
            return (!this.type || itemType === this.type)
                && (!this.status || itemStatus === this.status)
                && (!term || text.toLowerCase().includes(term));
        }
     }">
    <form method="GET"
          action="{{ route('master-data.items.index') }}"
          x-ref="filterForm"
          class="sticky top-[65px] lg:top-0 z-20 bg-gray-50/95 backdrop-blur pb-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <select name="type" x-model="type" @change="$refs.filterForm.submit()" class="sf-input text-base">
                <option value="">Semua Tipe</option>
                @foreach($itemTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" x-model="status" @change="$refs.filterForm.submit()" class="sf-input text-base">
                <option value="">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Non-Aktif</option>
            </select>

            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="search"
                       name="q"
                       x-model="search"
                       placeholder="Cari nama atau SKU..."
                       class="sf-input pl-10 text-base">
            </div>
        </div>
    </form>

    @if(request()->hasAny(['type', 'status', 'q']))
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm text-gray-500">
                Menampilkan {{ $items->count() }} dari {{ $items->total() }} item
            </p>
            <a href="{{ route('master-data.items.index') }}" class="text-sm font-semibold text-primary-800">Reset</a>
        </div>
    @endif

    <div class="lg:hidden space-y-3">
        @forelse($items as $item)
            @php
                $searchable = strtolower($item->canonical_sku.' '.$item->name.' '.$item->jenis?->name.' '.$item->category?->name.' '.$item->primaryDepartment?->name);
                $typeBadge = match($item->item_type) {
                    'WIP_L1' => 'badge-wip-l1',
                    'WIP_L2' => 'badge-wip-l2',
                    'WIP_L3' => 'badge-wip-l3',
                    'PACKAGING' => 'badge-packaging',
                    'MENU_ITEM' => 'badge-menu-item',
                    default => 'badge-active',
                };
                $statusKey = $item->is_active ? 'active' : 'inactive';
                $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
            @endphp
            <a href="{{ route('master-data.items.show', $item) }}"
               x-show="matches(@js($searchable), @js($item->item_type), @js($statusKey))"
               class="block sf-card p-4 active:scale-[.99] transition-transform">
                <div class="flex gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-xs font-semibold text-gray-400">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                        @else
                            IMG
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-gray-900 text-base truncate">{{ $item->name }}</h2>
                        <p class="text-sm text-gray-500 truncate">SKU: {{ $item->canonical_sku }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
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

                <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-gray-600">
                    <div>
                        <span class="text-gray-400">Dept</span>
                        <p class="font-semibold text-gray-800 truncate">{{ $item->primaryDepartment?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Outlets</span>
                        <p class="font-semibold text-gray-800">{{ $item->outlets_count }} outlet</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Satuan</span>
                        <p class="font-semibold text-gray-800">{{ $item->baseUnit?->abbreviation ?? $item->baseUnit?->code ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Stok</span>
                        <p class="font-semibold text-gray-800">-</p>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">
                        Rp {{ number_format((float) ($item->last_purchase_price ?? 0), 0, ',', '.') }}
                    </p>
                    <span class="text-sm font-semibold text-primary-800">Detail</span>
                </div>
            </a>
        @empty
            <x-sf.empty-state
                title="Belum ada item"
                description="Tambahkan item master sebelum operasional stok berjalan."
                :action="auth()->user()->can('manage_items') ? route('master-data.items.create') : null"
                actionLabel="+ Tambah Item"
            />
        @endforelse
    </div>

    <div class="hidden lg:block">
        @if($items->isEmpty())
            <x-sf.empty-state
                title="Belum ada item"
                description="Tambahkan item master sebelum operasional stok berjalan."
                :action="auth()->user()->can('manage_items') ? route('master-data.items.create') : null"
                actionLabel="+ Tambah Item"
            />
        @else
            <div class="sf-card overflow-hidden">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">No</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Foto</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">SKU</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Jenis</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kategori</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipe</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Satuan</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Dept. Utama</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Outlet</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($items as $index => $item)
                            @php
                                $searchable = strtolower($item->canonical_sku.' '.$item->name.' '.$item->jenis?->name.' '.$item->category?->name.' '.$item->primaryDepartment?->name);
                                $typeBadge = match($item->item_type) {
                                    'WIP_L1' => 'badge-wip-l1',
                                    'WIP_L2' => 'badge-wip-l2',
                                    'WIP_L3' => 'badge-wip-l3',
                                    'PACKAGING' => 'badge-packaging',
                                    'MENU_ITEM' => 'badge-menu-item',
                                    default => 'badge-active',
                                };
                                $statusKey = $item->is_active ? 'active' : 'inactive';
                                $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
                            @endphp
                            <tr x-show="matches(@js($searchable), @js($item->item_type), @js($statusKey))" class="odd:bg-white even:bg-gray-50/60">
                                <td class="px-4 py-3 text-gray-400">{{ $items->firstItem() + $index }}</td>
                                <td class="px-4 py-3">
                                    <div class="h-10 w-10 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center text-xs font-semibold text-gray-400">
                                        @if($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                        @else
                                            IMG
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $item->canonical_sku }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <p class="font-semibold text-gray-900">{{ $item->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $item->keterangan_pembeda ?: 'Stok: -' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if($item->jenis)
                                        <span class="{{ $item->jenis->badgeClass() }}">{{ $item->jenis->name }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3"><span class="{{ $typeBadge }}">{{ $item->item_type }}</span></td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $item->baseUnit?->code ?? '-' }}
                                    @if($item->inventoryUnit)
                                        <span class="text-gray-300">/</span> {{ $item->inventoryUnit->code }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->primaryDepartment?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $item->outlets_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $item->is_active ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->is_active ? 'AKTIF' : 'NON-AKTIF' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('master-data.items.show', $item) }}"
                                           class="sf-icon-action sf-icon-view"
                                           title="Detail {{ $item->name }}"
                                           aria-label="Detail {{ $item->name }}">
                                            <span aria-hidden="true">i</span>
                                        </a>
                                        @can('manage_items')
                                            <a href="{{ route('master-data.items.edit', $item) }}"
                                               class="sf-icon-action sf-icon-edit"
                                               title="Edit {{ $item->name }}"
                                               aria-label="Edit {{ $item->name }}">
                                                <span aria-hidden="true">E</span>
                                            </a>
                                            <form method="POST" action="{{ route('master-data.items.toggle-active', $item) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="sf-icon-action {{ $item->is_active ? 'sf-icon-danger-soft' : 'sf-icon-success-soft' }}"
                                                        title="{{ $item->is_active ? 'Non-aktifkan' : 'Aktifkan' }} {{ $item->name }}"
                                                        aria-label="{{ $item->is_active ? 'Non-aktifkan' : 'Aktifkan' }} {{ $item->name }}">
                                                    <span aria-hidden="true">{{ $item->is_active ? 'x' : '+' }}</span>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
