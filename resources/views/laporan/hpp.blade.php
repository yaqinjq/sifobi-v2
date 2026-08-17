@extends('layouts.app')

@section('title', 'Laporan HPP')

@section('content')
<x-sf.page-header title="Laporan HPP" subtitle="Breakdown biaya resep yang sudah disetujui">
    <x-slot:actions>
        <a href="{{ route('laporan.hpp.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.hpp') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <select name="brand_id" class="sf-input text-base min-h-11">
                <option value="">Semua brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <select name="menu_id" class="sf-input text-base min-h-11">
                <option value="">Semua menu</option>
                @foreach($menus as $menu)
                    <option value="{{ $menu->id }}" @selected((string) ($filters['menu_id'] ?? '') === (string) $menu->id)>{{ $menu->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    @forelse($recipes as $recipe)
        @php $hpp = $recipe->hpp(); @endphp
        <x-sf.card>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-gray-900">{{ $recipe->menu?->name }} <span class="text-xs text-gray-400">v{{ $recipe->version_number }}</span></p>
                    <p class="text-xs text-gray-500">{{ $recipe->menu?->brand?->name ?? '-' }} &middot; Disetujui {{ optional($recipe->approved_at)->format('d M Y') }}</p>
                </div>
                <a href="{{ route('production.recipes.show', $recipe) }}" class="text-xs text-primary-600 hover:underline shrink-0">Detail &rarr;</a>
            </div>
            <div class="mt-3 flex items-center justify-between rounded-xl bg-primary-50 px-3 py-2.5">
                <span class="text-sm font-semibold text-primary-800">HPP per Unit</span>
                <span class="font-bold text-primary-800">Rp {{ number_format((float) $hpp['hpp_per_unit'], 0, ',', '.') }}</span>
            </div>
            @if($recipe->outlets->isNotEmpty())
                <div class="flex flex-wrap gap-1 mt-2">
                    @foreach($recipe->outlets as $outlet)
                        <span class="badge-active text-xs">{{ $outlet->name }}</span>
                    @endforeach
                </div>
            @endif
        </x-sf.card>
    @empty
        <x-sf.card>
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada resep yang disetujui.</p>
            </div>
        </x-sf.card>
    @endforelse

    <div>{{ $recipes->links() }}</div>
</div>
@endsection
