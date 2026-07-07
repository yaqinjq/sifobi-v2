@extends('layouts.app')

@section('title', 'Open Stock')

@section('topbar')
<x-sf.page-header title="Open Stock" subtitle="{{ auth()->user()->outlet->name ?? 'Semua Outlet' }}">
    <x-slot:actions>
        @can('input_open_stock')
            <a href="{{ route('operations.open-stocks.create') }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary-700 hover:bg-primary-600 text-white transition-colors"
               aria-label="Input Stok Awal">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>
@endsection

@section('content')

{{-- ══ FILTER BAR ══ --}}
<form method="GET" action="{{ route('operations.open-stocks.index') }}"
      class="px-4 py-3 lg:px-6 flex flex-wrap gap-2">

    <select name="status" onchange="this.form.submit()"
            class="sf-input py-2 text-sm flex-shrink-0 w-auto">
        <option value="">Semua Status</option>
        @foreach(['DRAFT' => 'Draft', 'POSTED' => 'Posted', 'VOID' => 'Void'] as $val => $lbl)
            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
        @endforeach
    </select>

    <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()"
           class="sf-input py-2 text-sm flex-shrink-0 w-auto">

    <div class="relative flex-1 min-w-40">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="Cari item..."
               class="sf-input py-2 text-sm pl-9">
    </div>

    @if(request()->hasAny(['status', 'date', 'q']))
        <a href="{{ route('operations.open-stocks.index') }}"
           class="sf-btn-secondary py-2 text-sm">Reset</a>
    @endif
</form>

<div class="border-t border-gray-100 mx-4 lg:mx-6"></div>

{{-- ══ MOBILE: card list ══ --}}
<div class="md:hidden px-4 pt-4 space-y-3">
    @forelse($openStocks as $openStock)
        <a href="{{ route('operations.open-stocks.show', $openStock) }}"
           class="block sf-card p-4 active:scale-[.99] transition-transform">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900 truncate">
                        {{ $openStock->item?->name ?? '—' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $openStock->item?->canonical_sku }}</p>
                </div>
                @php
                    $badgeClass = match($openStock->status) {
                        'POSTED' => 'badge-posted',
                        'VOID'   => 'badge-void',
                        default  => 'badge-draft',
                    };
                @endphp
                <span class="{{ $badgeClass }} shrink-0">{{ $openStock->status }}</span>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <p class="text-gray-500 truncate">{{ $openStock->outlet?->name }}</p>
                <p class="text-gray-500 text-right">{{ $openStock->business_date->format('d/m/Y') }}</p>
                <p class="text-gray-500">{{ $openStock->targetLabel() }}</p>
                <p class="text-right">
                    <span class="font-semibold text-gray-800">{{ rtrim(rtrim((string)$openStock->qty_in_base_unit, '0'), '.') }}</span>
                    <span class="text-gray-400 text-xs">{{ $openStock->unit?->code }}</span>
                </p>
            </div>

            <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                <span>{{ $openStock->createdBy?->name ?? '—' }}</span>
                <span>{{ $openStock->created_at->diffForHumans() }}</span>
            </div>
        </a>
    @empty
        <x-sf.empty-state
            icon="📋"
            title="Belum ada Open Stock"
            description="Mulai dengan input stok awal pertama untuk outlet ini."
            :action="auth()->user()->can('input_open_stock') ? route('operations.open-stocks.create') : null"
            actionLabel="+ Input Stok Awal"
        />
    @endforelse
</div>

{{-- ══ DESKTOP: tabel ══ --}}
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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    + Input Stok Awal
                </a>
            </div>
        @endcan
    </div>

    @if($openStocks->isEmpty())
        <x-sf.empty-state
            icon="📋"
            title="Belum ada Open Stock"
            description="Mulai dengan input stok awal pertama untuk outlet ini."
            :action="auth()->user()->can('input_open_stock') ? route('operations.open-stocks.create') : null"
            actionLabel="+ Input Stok Awal"
        />
    @else
        <div class="sf-card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tanggal</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty Utuh</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty Ecer</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Input Oleh</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($openStocks as $idx => $openStock)
                    @php
                        $badgeClass = match($openStock->status) {
                            'POSTED' => 'badge-posted',
                            'VOID'   => 'badge-void',
                            default  => 'badge-draft',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $openStock->item?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $openStock->item?->canonical_sku }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $openStock->business_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                            {{ rtrim(rtrim((string)$openStock->qty_whole, '0'), '.') }}
                            <span class="text-xs text-gray-400 font-normal">{{ $openStock->item?->inventoryUnit?->code }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                            {{ rtrim(rtrim((string)$openStock->qty_loose, '0'), '.') }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $openStock->createdBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="{{ $badgeClass }}">{{ $openStock->status }}</span></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('operations.open-stocks.show', $openStock) }}"
                                   class="sf-btn-secondary text-xs px-3 py-1.5 min-h-0">Detail</a>

                                @if($openStock->status === 'DRAFT')
                                    @can('input_open_stock')
                                        <a href="{{ route('operations.open-stocks.edit', $openStock) }}"
                                           class="sf-btn-secondary text-xs px-3 py-1.5 min-h-0">Edit</a>
                                    @endcan
                                    @can('post_open_stock')
                                        <form method="POST" action="{{ route('operations.open-stocks.post', $openStock) }}">
                                            @csrf
                                            <button type="submit" class="sf-btn-primary text-xs px-3 py-1.5 min-h-0">Post</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
