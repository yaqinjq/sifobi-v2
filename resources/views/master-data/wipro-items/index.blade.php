@extends('layouts.app')

@section('title', 'Data Item Wipro')

@section('content')
<div x-data="{
    viewMode: ['list', 'grid'].includes(localStorage.getItem('sifobi_wipro_items_view_mode'))
        ? localStorage.getItem('sifobi_wipro_items_view_mode')
        : 'list',
    setViewMode(mode) {
        this.viewMode = mode;
        localStorage.setItem('sifobi_wipro_items_view_mode', mode);
    }
}">
<x-sf.page-header title="Data Item Wipro" subtitle="Item dari katalog Wipro — cuma untuk Purchase Order" back="{{ route('master-data.items.index') }}">
    <x-slot:actions>
        @can('manage_integrations')
            <a href="{{ route('settings.wipro-catalog.index') }}" class="sf-btn-secondary text-xs px-3 py-2 min-h-11">
                <i class="ti ti-upload text-xs" aria-hidden="true"></i>
                Upload Katalog Baru
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

<div class="p-4 lg:p-6 pb-24 w-full">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700 mb-4">
        Item di sini disinkron otomatis dari katalog Wipro (nama, satuan, status aktif tidak bisa diubah manual —
        akan ikut sinkronisasi berikutnya). Yang bisa Anda lengkapi cuma foto & keterangan.
    </div>

    <form method="GET" class="flex flex-wrap gap-3 items-center border-b border-gray-100 bg-white p-3 rounded-2xl mb-4">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama atau SKU..." class="sf-input text-base flex-1 min-w-[180px]">

        <select name="category_id" class="sf-input w-auto text-base min-h-11" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $categoryFilter === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="sf-btn-secondary text-sm min-h-11">Cari</button>

        @if(request()->hasAny(['q', 'category_id']))
            <a href="{{ route('master-data.wipro-items.index') }}" class="sf-btn-secondary text-sm min-h-11">
                <i class="ti ti-x text-xs" aria-hidden="true"></i>
                Reset
            </a>
        @endif
    </form>

    @if($items->isEmpty())
        <div class="py-16 text-center text-gray-400">
            <i class="ti ti-package mb-3 block text-4xl" aria-hidden="true"></i>
            <p class="text-sm">Belum ada item Wipro yang cocok.</p>
        </div>
    @else
        {{-- ── LIST VIEW ── --}}
        <div x-show="viewMode === 'list'">
            <div class="lg:hidden space-y-3">
                @foreach($items as $item)
                    @include('master-data.wipro-items._card', ['item' => $item])
                @endforeach
            </div>

            <div class="hidden lg:block overflow-x-auto rounded-2xl border border-gray-100 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Foto</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($items as $item)
                            @php
                                $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
                                $isCustomized = $item->track_stock || ! str_starts_with($item->category?->name ?? '', 'Wipro —');
                            @endphp
                            <tr class="odd:bg-white even:bg-gray-50/60">
                                <td class="px-4 py-3">
                                    <div class="h-10 w-10 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center">
                                        @if($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                        @else
                                            <i class="ti ti-photo text-gray-300 text-sm" aria-hidden="true"></i>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $item->canonical_sku }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <p class="font-semibold text-gray-900">{{ $item->name }}</p>
                                    @if($isCustomized)
                                        <span class="badge-blue text-[10px]">Disesuaikan manual</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $item->is_active ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('master-data.wipro-items.edit', $item) }}" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── GRID VIEW ── --}}
        <div x-show="viewMode === 'grid'" x-cloak style="display:none">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach($items as $item)
                    @php
                        $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
                    @endphp
                    <a href="{{ route('master-data.wipro-items.edit', $item) }}"
                       class="group overflow-hidden rounded-2xl border border-gray-100 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-lg">
                        <div class="relative bg-gray-50" style="aspect-ratio:1">
                            @if($photoUrl)
                                <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <i class="ti ti-package text-4xl text-gray-200" aria-hidden="true"></i>
                                </div>
                            @endif
                            <div class="absolute right-2.5 top-2.5">
                                <span class="block h-2 w-2 rounded-full {{ $item->is_active ? 'bg-green-500' : 'bg-gray-300' }} ring-2 ring-white"></span>
                            </div>
                        </div>
                        <div class="p-3">
                            <p class="mb-0.5 truncate font-mono text-xs text-gray-400">{{ $item->canonical_sku }}</p>
                            <p class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-gray-900">{{ $item->name }}</p>
                            @if($item->category)
                                <p class="mt-1 truncate text-xs text-gray-400">{{ $item->category->name }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</div>
</div>
@endsection
