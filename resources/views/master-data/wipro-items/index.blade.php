@extends('layouts.app')

@section('title', 'Data Item Wipro')

@section('content')
<x-sf.page-header title="Data Item Wipro" subtitle="Item dari katalog Wipro — cuma untuk Purchase Order" back="{{ route('master-data.items.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama atau SKU..." class="sf-input text-sm flex-1">
        <button type="submit" class="sf-btn-secondary min-h-11 px-4">Cari</button>
    </form>

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
        Item di sini disinkron otomatis dari katalog Wipro (nama, satuan, kategori tidak bisa diubah manual —
        akan tertimpa lagi saat sinkronisasi berikutnya). Yang bisa Anda lengkapi cuma foto & keterangan.
    </div>

    <x-sf.card title="Daftar Item Wipro" :padding="false">
        @if($items->isEmpty())
            <p class="text-sm text-gray-400 py-8 text-center">Belum ada item dari Wipro.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($items as $item)
                    <a href="{{ route('master-data.wipro-items.edit', $item) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="w-12 h-12 rounded-xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                            @if($item->photo)
                                <img src="{{ asset('storage/'.$item->photo) }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                            @else
                                <i class="ti ti-photo text-gray-300 text-lg" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ $item->name }}</p>
                            <p class="text-xs text-gray-500">{{ $item->canonical_sku }} &middot; {{ $item->category?->name ?? '-' }}</p>
                        </div>
                        <i class="ti ti-chevron-right text-gray-300 shrink-0" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
            <div class="p-3">{{ $items->links() }}</div>
        @endif
    </x-sf.card>
</div>
@endsection
