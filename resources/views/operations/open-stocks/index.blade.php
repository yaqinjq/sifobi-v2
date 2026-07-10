@extends('layouts.app')

@section('title', 'Open Stock')

@section('topbar')
<x-sf.page-header title="Open Stock" subtitle="{{ auth()->user()->outlet->name ?? 'Semua Outlet' }}">
    <x-slot:actions>
        @can('input_open_stock')
            <a href="{{ route('operations.open-stocks.create') }}"
               class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-700 hover:bg-primary-600 text-white transition-colors"
               aria-label="Input Stok Awal">
                <i class="ti ti-plus text-xl" aria-hidden="true"></i>
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>
@endsection

@section('content')
@php
    $sortUrl = fn (string $column): string => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc',
    ]);

    $formatQty = fn ($value, int $decimals = 3): string => number_format((float) ($value ?? 0), $decimals, '.', ',');
@endphp

<form method="GET" action="{{ route('operations.open-stocks.index') }}"
      class="px-4 py-3 lg:px-6 flex flex-wrap gap-2">
    <select name="status" onchange="this.form.submit()"
            class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">
        <option value="">Semua Status</option>
        @foreach(['DRAFT' => 'Draft', 'POSTED' => 'Posted', 'VOID' => 'Void'] as $val => $lbl)
            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
        @endforeach
    </select>

    <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()"
           class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">

    <div class="relative flex-1 min-w-40">
        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
           aria-hidden="true"></i>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="Cari item atau SKU..."
               class="sf-input py-2 text-sm pl-9 min-h-11">
    </div>

    @if(request()->hasAny(['status', 'date', 'q', 'sort', 'direction']))
        <a href="{{ route('operations.open-stocks.index') }}"
           class="sf-btn-secondary py-2 text-sm min-h-11">Reset</a>
    @endif
</form>

<div class="border-t border-gray-100 mx-4 lg:mx-6"></div>

<div class="md:hidden px-4 pt-4 space-y-3 pb-24">
    @forelse($openStocks as $openStock)
        @php
            $item = $openStock->item;
            $baseUnit = $item?->baseUnit?->abbreviation ?? $item?->baseUnit?->code ?? $openStock->unit?->code ?? '';
            $wholeUnit = $openStock->stock_target === 'OUTLET_WAREHOUSE'
                ? ($item?->purchaseUnit?->abbreviation ?? $item?->purchaseUnit?->code ?? $item?->inventoryUnit?->abbreviation ?? $item?->inventoryUnit?->code ?? '')
                : ($item?->inventoryUnit?->abbreviation ?? $item?->inventoryUnit?->code ?? '');
        @endphp
        <a href="{{ route('operations.open-stocks.show', $openStock) }}"
           class="block sf-card p-4 active:scale-[.99] transition-transform">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900 truncate">
                        {{ $item?->name ?? '-' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $item?->canonical_sku ?? '-' }}</p>
                </div>
                @if($openStock->status === 'POSTED')
                    <span class="badge-posted shrink-0">POSTED</span>
                @elseif($openStock->status === 'VOID')
                    <span class="badge-void shrink-0">VOID</span>
                @else
                    <span class="badge-draft shrink-0">DRAFT</span>
                @endif
            </div>

            <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-gray-400">Tanggal</span>
                    <p class="font-semibold text-gray-800">{{ $openStock->business_date?->format('d/m/Y') ?? '-' }}</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-400">Outlet</span>
                    <p class="font-semibold text-gray-800 truncate">{{ $openStock->outlet?->code ?? $openStock->outlet?->name ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-400">Dept</span>
                    <p class="font-semibold text-gray-800 truncate">{{ $openStock->department?->name ?? '-' }}</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-400">Target</span>
                    <p class="font-semibold text-gray-800">{{ $openStock->targetLabel() }}</p>
                </div>
            </div>

            <div class="mt-3 rounded-xl bg-gray-50 p-3 grid grid-cols-3 gap-2 text-center text-xs">
                <div>
                    <p class="text-gray-400">Utuh</p>
                    <p class="font-semibold text-gray-900">{{ $formatQty($openStock->qty_whole) }} {{ $wholeUnit }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Ecer</p>
                    <p class="font-semibold text-gray-900">{{ $formatQty($openStock->qty_loose) }} {{ $baseUnit }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Total</p>
                    <p class="font-semibold text-gray-900">{{ $formatQty($openStock->qty_in_base_unit ?? $openStock->qty_posted ?? 0) }} {{ $baseUnit }}</p>
                </div>
            </div>
        </a>
    @empty
        <x-sf.empty-state
            icon="STK"
            title="Belum ada Open Stock"
            description="Mulai dengan input stok awal pertama untuk outlet ini."
            :action="auth()->user()->can('input_open_stock') ? route('operations.open-stocks.create') : null"
            actionLabel="+ Input Stok Awal"
        />
    @endforelse

    @if($openStocks->hasPages())
        <div class="pt-2">
            {{ $openStocks->links() }}
        </div>
    @endif
</div>

<div class="hidden md:block px-6 pt-4 pb-8">
    <div class="hidden lg:flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold font-heading text-gray-900">Open Stock</h1>
            <p class="text-sm text-gray-500 mt-0.5">Stok awal per outlet</p>
        </div>
        @can('input_open_stock')
            <div class="flex items-center gap-2">
                <a href="{{ route('operations.open-stocks.import') }}" class="sf-btn-secondary">Import Excel</a>
                <a href="{{ route('operations.open-stocks.create') }}" class="sf-btn-primary">
                    <i class="ti ti-plus text-base" aria-hidden="true"></i>
                    + Input Stok Awal
                </a>
            </div>
        @endcan
    </div>

    @if($openStocks->count() === 0)
        <x-sf.empty-state
            icon="STK"
            title="Belum ada Open Stock"
            description="Mulai dengan input stok awal pertama untuk outlet ini."
            :action="auth()->user()->can('input_open_stock') ? route('operations.open-stocks.create') : null"
            actionLabel="+ Input Stok Awal"
        />
    @else
        <div class="sf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">No</th>
                            <th class="text-left px-4 py-3">
                                <a href="{{ $sortUrl('date') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Tanggal
                                    @if($sort === 'date')
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
                                <a href="{{ $sortUrl('outlet_code') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Outlet
                                    @if($sort === 'outlet_code')
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
                                <a href="{{ $sortUrl('department_name') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Dept
                                    @if($sort === 'department_name')
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
                                <a href="{{ $sortUrl('item_name') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Bahan Baku
                                    @if($sort === 'item_name')
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
                                <a href="{{ $sortUrl('stock_target') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Target Stok
                                    @if($sort === 'stock_target')
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
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">QTY Utuh</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">QTY Ecer</th>
                            <th class="text-right px-4 py-3">
                                <a href="{{ $sortUrl('qty_in_base_unit') }}" class="group flex items-center justify-end gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    QTY Total
                                    @if($sort === 'qty_in_base_unit')
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
                                <a href="{{ $sortUrl('status') }}" class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-700 transition-colors">
                                    Status
                                    @if($sort === 'status')
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($openStocks as $idx => $openStock)
                            @php
                                $item = $openStock->item;
                                $baseUnit = $item?->baseUnit?->abbreviation ?? $item?->baseUnit?->code ?? $openStock->unit?->code ?? '';
                                $wholeUnit = $openStock->stock_target === 'OUTLET_WAREHOUSE'
                                    ? ($item?->purchaseUnit?->abbreviation ?? $item?->purchaseUnit?->code ?? $item?->inventoryUnit?->abbreviation ?? $item?->inventoryUnit?->code ?? '')
                                    : ($item?->inventoryUnit?->abbreviation ?? $item?->inventoryUnit?->code ?? '');
                            @endphp
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ ($openStocks->firstItem() ?? 1) + $idx }}</td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $openStock->business_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <p class="font-semibold text-gray-900">{{ $openStock->outlet?->code ?? '-' }}</p>
                                    <p class="text-xs text-gray-400">{{ $openStock->outlet?->name ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $openStock->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $item?->name ?? '-' }}</p>
                                    <p class="text-xs text-gray-400">{{ $item?->canonical_sku ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if($openStock->stock_target === 'OUTLET_WAREHOUSE')
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Gudang</span>
                                    @else
                                        <span class="badge-active">Harian</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                    {{ $formatQty($openStock->qty_whole) }}
                                    <span class="text-xs text-gray-400 font-normal">{{ $wholeUnit }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                    {{ $formatQty($openStock->qty_loose) }}
                                    <span class="text-xs text-gray-400 font-normal">{{ $baseUnit }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">
                                    {{ $formatQty($openStock->qty_in_base_unit ?? $openStock->qty_posted ?? 0) }}
                                    <span class="text-xs text-gray-400 font-normal">{{ $baseUnit }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($openStock->status === 'POSTED')
                                        <span class="badge-posted">POSTED</span>
                                    @elseif($openStock->status === 'VOID')
                                        <span class="badge-void">VOID</span>
                                    @else
                                        <span class="badge-draft">DRAFT</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <x-icon-btn icon="view" label="Detail" color="gray" size="sm"
                                                    href="{{ route('operations.open-stocks.show', $openStock) }}" />

                                        @if($openStock->status === 'DRAFT')
                                            @can('input_open_stock')
                                                <x-icon-btn icon="edit" label="Edit" color="blue" size="sm"
                                                            href="{{ route('operations.open-stocks.edit', $openStock) }}" />
                                            @endcan
                                            @can('post_open_stock')
                                                <x-icon-btn icon="post" label="Post" color="green" size="sm"
                                                            href="{{ route('operations.open-stocks.post', $openStock) }}"
                                                            method="POST" />
                                            @endcan
                                        @elseif($openStock->status === 'POSTED')
                                            @can('void_open_stock')
                                                <x-icon-btn icon="void" label="Void" color="red" size="sm"
                                                            href="{{ route('operations.open-stocks.void', $openStock) }}"
                                                            method="POST"
                                                            confirm="Void open stock ini?" />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $openStocks->links() }}
        </div>
    @endif
</div>
@endsection
