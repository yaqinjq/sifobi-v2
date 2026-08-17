@extends('layouts.app')

@section('title', 'Stok Menipis')

@section('content')
<x-sf.page-header title="Stok Menipis" subtitle="Item dengan saldo di bawah minimum stok"
    back="{{ route('laporan.index') }}">
    <x-slot:actions>
        <a href="{{ route('laporan.stok-menipis.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">

    {{-- Filter --}}
    <x-sf.card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="sf-label">Outlet</label>
                <select name="outlet_id" class="sf-input text-sm">
                    <option value="">Semua Outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected(($filters['outlet_id'] ?? '') == $outlet->id)>
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sf-label">Kategori</label>
                <select name="category_id" class="sf-input text-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="sf-btn-primary min-h-11 w-full">Tampilkan</button>
            </div>
        </form>
    </x-sf.card>

    {{-- Summary badge --}}
    <div class="flex items-center gap-3">
        @if($lowStockItems->isEmpty())
            <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2 w-full">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Semua item di atas minimum stok. Stok aman!
            </div>
        @else
            <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center gap-2 w-full">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><strong>{{ $lowStockItems->count() }} item</strong> di bawah minimum stok</span>
            </div>
        @endif
    </div>

    {{-- Table --}}
    @if($lowStockItems->isNotEmpty())
        <x-sf.card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left">
                            <th class="px-4 py-3 font-semibold text-gray-600 text-xs">Item</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-xs">Outlet</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-xs text-right">Stok Saat Ini</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-xs text-right">Minimum</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-xs text-right">Kekurangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($lowStockItems as $row)
                            @php
                                $pct = $row->min_stock > 0 ? ($row->qty_on_hand / $row->min_stock) * 100 : 0;
                                $barColor = $pct <= 0 ? 'bg-red-500' : ($pct < 50 ? 'bg-orange-400' : 'bg-yellow-400');
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ $row->item_name }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $row->canonical_sku }}</p>
                                    <p class="text-xs text-gray-400">{{ $row->category }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->outlet_name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <p class="font-semibold {{ $row->qty_on_hand <= 0 ? 'text-red-600' : 'text-orange-600' }}">
                                        {{ rtrim(rtrim(number_format((float) $row->qty_on_hand, 6), '0'), '.') }}
                                        <span class="text-xs text-gray-400">{{ $row->unit }}</span>
                                    </p>
                                    <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden w-20 ml-auto">
                                        <div class="{{ $barColor }} h-full rounded-full transition-all"
                                             style="width: {{ min(100, max(0, $pct)) }}%"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">
                                    {{ rtrim(rtrim(number_format((float) $row->min_stock, 6), '0'), '.') }}
                                    <span class="text-xs text-gray-400">{{ $row->unit }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold text-red-600">
                                        {{ rtrim(rtrim(number_format((float) $row->kekurangan, 6), '0'), '.') }}
                                        <span class="text-xs text-gray-400">{{ $row->unit }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    @endif

    {{-- Info: cara atur min stok --}}
    <x-sf.card title="Cara Mengatur Minimum Stok">
        <p class="text-sm text-gray-600">
            Minimum stok diatur per item di halaman
            <a href="{{ route('master-data.items.index') }}" class="text-primary-700 underline font-medium">Master Data &rarr; Item</a>.
            Buka detail item, lalu ubah field <strong>Minimum Stok</strong>.
            Item dengan nilai 0 tidak akan muncul di halaman ini.
        </p>
    </x-sf.card>

</div>
@endsection
