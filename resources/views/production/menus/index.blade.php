@extends('layouts.app')

@section('title', 'Menu & Resep')

@php
    $rupiah = fn ($value) => $value === null ? null : 'Rp '.number_format((float) $value, 0, ',', '.');
    $hasActiveFilter = $filters['q'] !== '' || $filters['brand_id'] || $filters['category'] !== '' || $filters['recipe'] !== '' || $filters['status'] !== 'active';
    $categoryUrl = fn ($value) => request()->fullUrlWithQuery(['category' => $value, 'page' => null]);
    $uncategorizedCount = (int) ($categoryCounts[''] ?? $categoryCounts[null] ?? 0);
    // Huruf/angka pertama -- nama seperti "(LESS SUGAR 50%) HKT" jangan jadi "(".
    $initial = fn (string $name) => preg_match('/[\p{L}\p{N}]/u', $name, $m) ? mb_strtoupper($m[0]) : '?';
@endphp

@section('content')
<x-sf.page-header
    title="Menu & Resep"
    subtitle="Daftar menu, status resep, dan HPP"
    back="{{ route('dashboard') }}"
>
    <x-slot:actions>
        {{-- Label disembunyikan di HP supaya 3 tombol muat 1 baris --}}
        <a href="{{ route('production.hpp-calculator.index') }}" class="sf-btn-secondary min-h-11 px-3 text-xs inline-flex items-center gap-1" title="Kalkulator HPP">
            <i class="ti ti-calculator text-base" aria-hidden="true"></i>
            <span class="hidden sm:inline-flex">Kalkulator HPP</span>
        </a>
        <a href="{{ route('production.menus.import-form') }}" class="sf-btn-secondary min-h-11 px-3 text-xs inline-flex items-center gap-1" title="Import Excel">
            <i class="ti ti-file-import text-base" aria-hidden="true"></i>
            <span class="hidden sm:inline-flex">Import Excel</span>
        </a>
        <a href="{{ route('production.menus.create') }}" class="sf-btn-primary min-h-11 px-3 inline-flex items-center gap-1">
            <i class="ti ti-plus text-base" aria-hidden="true"></i>
            <span>Tambah Menu</span>
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4" x-data="{ selected: [] }">
    @if(session('import_errors'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold mb-1">{{ count(session('import_errors')) }} baris tidak diimport:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach(array_slice(session('import_errors'), 0, 20) as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Pencarian & filter --}}
    <x-sf.card>
        <form method="GET" action="{{ route('production.menus.index') }}" class="space-y-3">
            @if($filters['category'] !== '')
                <input type="hidden" name="category" value="{{ $filters['category'] }}">
            @endif
            <div class="relative">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="Cari nama atau kode menu..."
                       class="sf-input text-base min-h-11 pl-10">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @if($brands->count() > 1)
                    <select name="brand_id" class="sf-input text-base min-h-11">
                        <option value="">Semua brand</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((int) $filters['brand_id'] === $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="recipe" class="sf-input text-base min-h-11">
                    <option value="">Semua status resep</option>
                    @foreach($recipeFilters as $value => $label)
                        <option value="{{ $value }}" @selected($filters['recipe'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="sf-input text-base min-h-11">
                    <option value="active" @selected($filters['status'] === 'active')>Menu aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Menu nonaktif</option>
                    <option value="all" @selected($filters['status'] === 'all')>Aktif &amp; nonaktif</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="sf-btn-primary min-h-11 flex-1">Cari</button>
                    @if($hasActiveFilter)
                        <a href="{{ route('production.menus.index') }}" class="sf-btn-secondary min-h-11 px-4 inline-flex items-center">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Chip kategori (angka ikut pencarian/filter di atas). Diurut dari
             yang paling banyak menunya; selebihnya disembunyikan di balik
             toggle supaya tidak memakan banyak baris (bisa puluhan kategori). --}}
        @php
            $sortedCategories = $categories->sortByDesc(fn ($category) => $categoryCounts[$category->id] ?? 0)->values();
            $visibleChips = 10;
        @endphp
        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100" x-data="{ showAll: false }">
            <a href="{{ $categoryUrl(null) }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold border {{ $filters['category'] === '' ? 'bg-primary-700 text-white border-primary-700' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                Semua <span class="opacity-75">({{ $totalCount }})</span>
            </a>
            @foreach($sortedCategories as $index => $category)
                @php $isActiveChip = $filters['category'] === (string) $category->id; @endphp
                <a href="{{ $categoryUrl($category->id) }}"
                   @if($index >= $visibleChips && ! $isActiveChip) x-show="showAll" x-cloak @endif
                   class="rounded-full px-3 py-1.5 text-xs font-semibold border {{ $isActiveChip ? 'bg-primary-700 text-white border-primary-700' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    {{ $category->name }} <span class="opacity-75">({{ $categoryCounts[$category->id] ?? 0 }})</span>
                </a>
            @endforeach
            @if($sortedCategories->count() > $visibleChips)
                <button type="button" @click="showAll = !showAll"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-gray-50"
                        x-text="showAll ? 'Sembunyikan' : '+ {{ $sortedCategories->count() - $visibleChips }} kategori lainnya'"></button>
            @endif
            @if($uncategorizedCount > 0)
                <a href="{{ $categoryUrl('none') }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold border {{ $filters['category'] === 'none' ? 'bg-primary-700 text-white border-primary-700' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    Tanpa kategori <span class="opacity-75">({{ $uncategorizedCount }})</span>
                </a>
            @endif
        </div>
    </x-sf.card>

    <p class="text-sm text-gray-500">
        @if($menus->total() > 0)
            Menampilkan {{ $menus->firstItem() }}–{{ $menus->lastItem() }} dari <strong class="text-gray-900">{{ $menus->total() }}</strong> menu
        @else
            Tidak ada menu yang cocok.
        @endif
    </p>

    {{-- Kartu (mobile) --}}
    <div class="lg:hidden space-y-3">
        @forelse($menus as $menu)
            @php
                $latest = $menu->recipes->first();
                $hpp = $hppByMenu[$menu->id] ?? null;
                $foodCost = ($hpp !== null && (float) $menu->selling_price > 0) ? round((float) $hpp / (float) $menu->selling_price * 100, 1) : null;
            @endphp
            <x-sf.card>
                <div class="flex items-start gap-3">
                    <input type="checkbox" x-model="selected" value="{{ $menu->id }}" class="mt-1 rounded border-gray-300 text-primary-700 shrink-0">
                    <a href="{{ route('production.menus.show', $menu) }}" class="flex gap-3 min-w-0 flex-1">
                        @if($menu->photoUrl())
                            <img src="{{ $menu->photoUrl() }}" alt="{{ $menu->name }}" class="w-12 h-12 rounded-xl object-cover shrink-0">
                        @else
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-sm font-semibold shrink-0 {{ $menu->category?->placeholderClass() ?? 'ph-gray' }}">
                                {{ $initial($menu->name) }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">{{ $menu->name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $menu->code ?? '-' }}
                                @if($menu->category) &middot; {{ $menu->category->name }} @endif
                                @if($brands->count() > 1) &middot; {{ $menu->brand?->name }} @endif
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
                                @if($latest)
                                    <span class="{{ $latest->statusBadgeClass() }}">v{{ $latest->version_number }} {{ $latest->statusLabel() }}</span>
                                @else
                                    <span class="badge-draft">Belum ada resep</span>
                                @endif
                                @unless($menu->is_active)
                                    <span class="badge-inactive">Nonaktif</span>
                                @endunless
                            </div>
                            <p class="text-xs text-gray-600 mt-2">
                                Harga: <strong>{{ $rupiah($menu->selling_price) ?? '-' }}</strong>
                                &middot; HPP: <strong>{{ $rupiah($hpp) ?? '-' }}</strong>
                                @if($foodCost !== null) &middot; Food cost {{ $foodCost }}% @endif
                            </p>
                        </div>
                    </a>
                </div>
            </x-sf.card>
        @empty
            <x-sf.card>
                <div class="text-center py-8 text-gray-400">
                    <p class="text-sm">{{ $hasActiveFilter ? 'Tidak ada menu yang cocok dengan pencarian/filter.' : 'Belum ada menu. Tambah menu atau import dari Excel.' }}</p>
                </div>
            </x-sf.card>
        @endforelse
    </div>

    {{-- Tabel (desktop) --}}
    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-10">
                                @if($menus->isNotEmpty())
                                    <input type="checkbox" class="rounded border-gray-300 text-primary-700"
                                           :checked="selected.length === {{ $menus->count() }}"
                                           @change="selected = $event.target.checked ? @js($menus->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->values()) : []">
                                @endif
                            </th>
                            <x-sf.sortable-th column="name" :sort="$sort" :direction="$direction">Menu</x-sf.sortable-th>
                            <x-sf.sortable-th column="code" :sort="$sort" :direction="$direction">Kode</x-sf.sortable-th>
                            <th class="px-4 py-3">Kategori</th>
                            <x-sf.sortable-th column="selling_price" :sort="$sort" :direction="$direction" align="right">Harga Jual</x-sf.sortable-th>
                            <th class="px-4 py-3 text-right">HPP</th>
                            <th class="px-4 py-3 text-right">Food Cost</th>
                            <th class="px-4 py-3">Resep</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($menus as $menu)
                            @php
                                $latest = $menu->recipes->first();
                                $hpp = $hppByMenu[$menu->id] ?? null;
                                $foodCost = ($hpp !== null && (float) $menu->selling_price > 0) ? round((float) $hpp / (float) $menu->selling_price * 100, 1) : null;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" x-model="selected" value="{{ $menu->id }}" class="rounded border-gray-300 text-primary-700">
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('production.menus.show', $menu) }}" class="flex items-center gap-3 min-w-0">
                                        @if($menu->photoUrl())
                                            <img src="{{ $menu->photoUrl() }}" alt="{{ $menu->name }}" class="w-9 h-9 rounded-xl object-cover shrink-0">
                                        @else
                                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-semibold shrink-0 {{ $menu->category?->placeholderClass() ?? 'ph-gray' }}">
                                                {{ $initial($menu->name) }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 hover:text-primary-700">{{ $menu->name }}</p>
                                            <p class="text-xs text-gray-400">
                                                @if($brands->count() > 1){{ $menu->brand?->name }}@endif
                                                @unless($menu->is_active)<span class="badge-inactive">Nonaktif</span>@endunless
                                            </p>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $menu->code ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $menu->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ $rupiah($menu->selling_price) ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ $rupiah($hpp) ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($foodCost === null)
                                        <span class="text-gray-400">-</span>
                                    @elseif($foodCost <= 35)
                                        <span class="badge-approved">{{ $foodCost }}%</span>
                                    @elseif($foodCost <= 45)
                                        <span class="badge-pending">{{ $foodCost }}%</span>
                                    @else
                                        <span class="badge-rejected">{{ $foodCost }}%</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($latest)
                                        <span class="{{ $latest->statusBadgeClass() }}">v{{ $latest->version_number }} {{ $latest->statusLabel() }}</span>
                                    @else
                                        <span class="badge-draft">Belum ada</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                    {{ $hasActiveFilter ? 'Tidak ada menu yang cocok dengan pencarian/filter.' : 'Belum ada menu. Tambah menu atau import dari Excel.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    <x-sf.bulk-action-bar
        route="{{ route('production.menus.bulk-destroy') }}"
        confirm-message="Hapus __COUNT__ menu terpilih? Menu yang masih punya riwayat resep atau catatan spoil akan dinonaktifkan, bukan dihapus permanen."
        button-label="Hapus Terpilih"
        button-icon="ti-trash"
    />

    <div class="flex items-center justify-between gap-2 flex-wrap">
        <x-sf.per-page-selector :options="$perPageOptions" :current="$perPage" />
        {{ $menus->links() }}
    </div>
</div>
@endsection
