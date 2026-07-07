@extends('layouts.app')

@section('title', 'Ringkasan Stok Semua Outlet')

@section('content')
<x-sf.page-header title="Ringkasan Stok Semua Outlet" subtitle="Nilai stok per outlet dan kategori" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.stok-summary') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <select name="brand_id" class="sf-input text-base min-h-11">
                <option value="">Semua brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <select name="outlet_id" class="sf-input text-base min-h-11">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <select name="category_id" class="sf-input text-base min-h-11">
                <option value="">Semua kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($outlets as $outlet)
            @php $card = $outletCards->get($outlet->id); @endphp
            <x-sf.card>
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-gray-900">{{ $outlet->name }}</p>
                        @if((int) ($card->empty_items ?? 0) > 0)
                            <span class="badge-rejected">{{ (int) $card->empty_items }} kosong</span>
                        @else
                            <span class="badge-active">OK</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500">{{ number_format((int) ($card->total_items ?? 0)) }} item</p>
                    <p class="text-xl font-bold text-gray-900">Rp {{ number_format((float) ($card->total_value ?? 0), 0, ',', '.') }}</p>
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Outlet belum ada" description="Aktifkan outlet terlebih dahulu di pengaturan." />
        @endforelse
    </div>

    <x-sf.card title="Breakdown Kategori x Outlet" padding="false">
        <div class="overflow-x-auto">
            @php
                $categoriesInRows = $breakdown->pluck('category')->unique()->values();
                $grandTotal = 0;
            @endphp
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Kategori</th>
                        @foreach($outlets as $outlet)
                            <th class="px-4 py-3 text-right">{{ $outlet->name }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($categoriesInRows as $categoryName)
                        @php $rowTotal = 0; @endphp
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $categoryName }}</td>
                            @foreach($outlets as $outlet)
                                @php
                                    $cell = $breakdown->first(fn ($row) => $row->category === $categoryName && (int) $row->outlet_id === (int) $outlet->id);
                                    $value = (float) ($cell->total_value ?? 0);
                                    $rowTotal += $value;
                                    $grandTotal += $value;
                                @endphp
                                <td class="px-4 py-3 text-right">Rp {{ number_format($value, 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $outlets->count() + 2 }}" class="px-4 py-10 text-center text-gray-500">Belum ada saldo stok.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($categoriesInRows->isNotEmpty())
                    <tfoot class="bg-gray-50 text-sm font-semibold text-gray-900">
                        <tr>
                            <td class="px-4 py-3">TOTAL</td>
                            @foreach($outlets as $outlet)
                                @php
                                    $outletTotal = $breakdown
                                        ->where('outlet_id', $outlet->id)
                                        ->sum(fn ($row) => (float) $row->total_value);
                                @endphp
                                <td class="px-4 py-3 text-right">Rp {{ number_format($outletTotal, 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-sf.card>
</div>
@endsection
