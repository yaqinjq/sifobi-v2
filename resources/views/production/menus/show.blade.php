@extends('layouts.app')

@section('title', $menu->name)

@section('content')
<x-sf.page-header
    title="{{ $menu->name }}"
    subtitle="{{ $menu->brand?->name ?? '-' }} &middot; Histori Resep"
    back="{{ route('production.menus.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-5">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-end gap-2">
        <form method="POST" action="{{ route('production.menus.destroy', $menu) }}"
              onsubmit="return confirm('{{ $menu->canHardDelete() ? 'Hapus menu ini?' : 'Menu ini masih punya riwayat resep, jadi tidak bisa dihapus permanen — nonaktifkan menu ini?' }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="sf-btn-secondary inline-flex min-h-11 items-center gap-2 text-red-600">
                <i class="ti ti-trash text-base" aria-hidden="true"></i>
                <span>Hapus Menu</span>
            </button>
        </form>
        <a href="{{ route('production.recipes.create', $menu) }}" class="sf-btn-primary inline-flex min-h-11 items-center gap-2">
            <i class="ti ti-plus text-base" aria-hidden="true"></i>
            <span>Resep Versi Baru</span>
        </a>
    </div>

    <x-sf.card title="Histori Versi Resep">
        @if($menu->recipes->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada resep untuk menu ini.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($menu->recipes as $recipe)
                    <div class="flex items-center gap-2 px-3 py-3 hover:bg-gray-50 transition-colors">
                        <a href="{{ route('production.recipes.show', $recipe) }}" class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-gray-900">Versi {{ $recipe->version_number }}</p>
                                <span class="{{ $recipe->statusBadgeClass() }} text-xs">{{ $recipe->statusLabel() }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Dibuat oleh {{ $recipe->createdBy?->name ?? '-' }}
                                &middot; {{ $recipe->created_at->format('d M Y') }}
                            </p>
                            @if($recipe->status === \App\Modules\Production\Models\Recipe::STATUS_APPROVED && $recipe->outlets->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($recipe->outlets as $outlet)
                                        <span class="badge-active text-xs">{{ $outlet->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </a>
                        @if($recipe->canDelete())
                            <form method="POST" action="{{ route('production.recipes.destroy', $recipe) }}"
                                  onsubmit="return confirm('Hapus draft resep versi {{ $recipe->version_number }} ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shrink-0 p-2 text-gray-400 hover:text-red-600" aria-label="Hapus draft resep">
                                    <i class="ti ti-trash text-base" aria-hidden="true"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
