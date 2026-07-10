@extends('layouts.app')

@section('title', 'Master Item')

@section('content')
@php
    $sortUrl = fn (string $column): string => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc',
    ]);
@endphp

<div x-data="{
    viewMode: ['list', 'grid'].includes(localStorage.getItem('sifobi_items_view_mode'))
        ? localStorage.getItem('sifobi_items_view_mode')
        : 'list',
    setViewMode(mode) {
        this.viewMode = mode;
        localStorage.setItem('sifobi_items_view_mode', mode);
    }
}">
<x-sf.page-header title="Master Item" subtitle="Bahan Baku & Produk">
    <x-slot:actions>
        @can('manage_items')
            <a href="{{ route('master-data.items.create') }}"
               class="sf-btn-primary text-xs px-3 py-2 min-h-11">
                + Tambah Item
            </a>
        @endcan
        <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1">
            <button type="button"
                    @click="setViewMode('list')"
                    :style="viewMode === 'list' ? 'background:#FFFFFF;color:#111827;box-shadow:0 1px 2px rgba(15,23,42,.08)' : 'background:transparent;color:#9CA3AF'"
                    class="flex h-11 w-11 items-center justify-center rounded-lg transition-all"
                    title="List view"
                    aria-label="Tampilan list">
                <i class="ti ti-list text-base" aria-hidden="true"></i>
            </button>
            <button type="button"
                    @click="setViewMode('grid')"
                    :style="viewMode === 'grid' ? 'background:#FFFFFF;color:#111827;box-shadow:0 1px 2px rgba(15,23,42,.08)' : 'background:transparent;color:#9CA3AF'"
                    class="flex h-11 w-11 items-center justify-center rounded-lg transition-all"
                    title="Grid view"
                    aria-label="Tampilan grid">
                <i class="ti ti-layout-grid text-base" aria-hidden="true"></i>
            </button>
        </div>
    </x-slot:actions>
</x-sf.page-header>

<div class="p-4 lg:p-6 pb-24 w-full"
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

    <div id="items-list-view" x-show="viewMode === 'list'">
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
            <div x-show="matches(@js($searchable), @js($item->item_type), @js($statusKey))"
                 class="sf-card p-4">
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
                    <x-icon-btn
                        icon="view"
                        label="Detail"
                        color="gray"
                        href="{{ route('master-data.items.show', $item) }}"
                    />
                </div>
            </div>
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
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('canonical_sku') }}"
                                   class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700">
                                    SKU
                                    @if($sort === 'canonical_sku')
                                        @if($direction === 'asc')
                                            <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
                                        @else
                                            <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
                                        @endif
                                    @else
                                        <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('name') }}"
                                   class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700">
                                    Nama
                                    @if($sort === 'name')
                                        @if($direction === 'asc')
                                            <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
                                        @else
                                            <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
                                        @endif
                                    @else
                                        <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('item_jenis_id') }}"
                                   class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700">
                                    Jenis
                                    @if($sort === 'item_jenis_id')
                                        @if($direction === 'asc')
                                            <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
                                        @else
                                            <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
                                        @endif
                                    @else
                                        <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('item_category_id') }}"
                                   class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700">
                                    Kategori
                                    @if($sort === 'item_category_id')
                                        @if($direction === 'asc')
                                            <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
                                        @else
                                            <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
                                        @endif
                                    @else
                                        <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('item_type') }}"
                                   class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700">
                                    Tipe
                                    @if($sort === 'item_type')
                                        @if($direction === 'asc')
                                            <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
                                        @else
                                            <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
                                        @endif
                                    @else
                                        <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
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
                                        <x-icon-btn
                                            icon="view"
                                            label="Detail {{ $item->name }}"
                                            color="gray"
                                            size="sm"
                                            href="{{ route('master-data.items.show', $item) }}"
                                        />
                                        @can('manage_items')
                                            <x-icon-btn
                                                icon="edit"
                                                label="Edit {{ $item->name }}"
                                                color="blue"
                                                size="sm"
                                                href="{{ route('master-data.items.edit', $item) }}"
                                            />
                                            <x-icon-btn
                                                icon="toggle"
                                                :label="($item->is_active ? 'Non-aktifkan ' : 'Aktifkan ').$item->name"
                                                :color="$item->is_active ? 'red' : 'green'"
                                                size="sm"
                                                href="{{ route('master-data.items.toggle-active', $item) }}"
                                                method="PATCH"
                                            />
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

    <div id="items-grid-view" x-show="viewMode === 'grid'" x-cloak style="display:none">
        <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse($items as $item)
                @php
                    $searchable = strtolower($item->canonical_sku.' '.$item->name.' '.$item->jenis?->name.' '.$item->category?->name.' '.$item->primaryDepartment?->name);
                    $statusKey = $item->is_active ? 'active' : 'inactive';
                    $jenisBadge = [
                        'RAW_MATERIAL' => 'bg-green-600 text-white',
                        'DRYGOOD' => 'bg-amber-500 text-white',
                        'WIP' => 'bg-blue-600 text-white',
                        'NON_RAW_MATERIAL' => 'bg-gray-500 text-white',
                        'MENU' => 'bg-rose-600 text-white',
                    ];
                    $jenisClass = $jenisBadge[$item->jenis?->code] ?? 'bg-gray-500 text-white';
                    $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
                @endphp
                <a href="{{ route('master-data.items.show', $item) }}"
                   x-show="matches(@js($searchable), @js($item->item_type), @js($statusKey))"
                   class="group overflow-hidden rounded-2xl border border-gray-100 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-lg">
                    <div class="relative bg-gray-50" style="aspect-ratio:1">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}"
                                 alt="{{ $item->name }}"
                                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        @else
                            <div class="flex h-full w-full items-center justify-center">
                                <i class="ti ti-package text-4xl text-gray-200" aria-hidden="true"></i>
                            </div>
                        @endif

                        <div class="absolute right-2.5 top-2.5">
                            @if($item->is_active)
                                <span class="block h-2 w-2 rounded-full bg-green-500 ring-2 ring-white"></span>
                            @else
                                <span class="block h-2 w-2 rounded-full bg-gray-300 ring-2 ring-white"></span>
                            @endif
                        </div>

                        @if($item->jenis)
                            <div class="absolute bottom-0 left-0 right-0 px-2 pb-2">
                                <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold backdrop-blur-sm {{ $jenisClass }}">
                                    {{ $item->jenis->name }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="p-3">
                        <p class="mb-0.5 truncate font-mono text-xs text-gray-400">
                            {{ $item->canonical_sku }}
                        </p>
                        <p class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-gray-900">
                            {{ $item->name }}
                        </p>
                        @if($item->category)
                            <p class="mt-1 truncate text-xs text-gray-400">
                                {{ $item->category->name }}
                            </p>
                        @endif
                    </div>
                </a>
            @empty
                <div class="col-span-full py-12 text-center text-gray-400">
                    <i class="ti ti-package mb-3 block text-4xl" aria-hidden="true"></i>
                    <p class="text-sm">Belum ada item</p>
                </div>
            @endforelse
        </div>

        <div class="px-4 pb-4">
            {{ $items->links() }}
        </div>
    </div>
</div>
</div>
@endsection
