@extends('layouts.app')

@section('title', 'Menu & Resep')

@section('content')
<x-sf.page-header
    title="Menu & Resep"
    subtitle="Riset resep (R&D) dan HPP per menu"
    back="{{ route('dashboard') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-5">
    <div class="flex flex-wrap justify-end gap-2">
        <a href="{{ route('production.hpp-calculator.index') }}" class="sf-btn-secondary inline-flex min-h-11 items-center gap-2">
            <i class="ti ti-calculator text-base" aria-hidden="true"></i>
            <span>Coba Kalkulator HPP Dulu</span>
        </a>
        <a href="{{ route('production.menus.create') }}" class="sf-btn-primary inline-flex min-h-11 items-center gap-2">
            <i class="ti ti-plus text-base" aria-hidden="true"></i>
            <span>Tambah Menu</span>
        </a>
    </div>

    <x-sf.card title="Daftar Menu">
        @if($menus->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada menu. Tambah menu dulu sebelum bikin resep.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($menus as $menu)
                    <a href="{{ route('production.menus.show', $menu) }}" class="flex items-center justify-between gap-3 px-3 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $menu->name }}</p>
                            <p class="text-xs text-gray-500">{{ $menu->brand?->name ?? '-' }} @if($menu->code) &middot; {{ $menu->code }} @endif</p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0">{{ $menu->recipes_count }} versi resep</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-sf.card>

    <div>{{ $menus->links() }}</div>
</div>
@endsection
