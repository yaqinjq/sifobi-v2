@extends('layouts.app')

@section('title', 'POS - Order Aktif')

@section('content')
<x-sf.page-header
    title="POS - Order Aktif"
    subtitle="Order yang sedang berjalan hari ini"
    back="{{ route('dashboard') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <select name="outlet_id" class="sf-input text-sm" onchange="this.form.submit()">
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" @selected($o->id == $outletId)>{{ $o->name }}</option>
                @endforeach
            </select>
        </form>

        <div class="flex gap-2">
            @can('manage_pos_layout')
                <a href="{{ route('pos.layout.index', ['outlet_id' => $outletId]) }}" class="sf-btn-secondary inline-flex min-h-11 items-center gap-2">
                    <i class="ti ti-layout-grid text-base" aria-hidden="true"></i>
                    <span>Atur Denah</span>
                </a>
            @endcan
            <a href="{{ route('pos.orders.create', ['outlet_id' => $outletId]) }}" class="sf-btn-primary inline-flex min-h-11 items-center gap-2">
                <i class="ti ti-plus text-base" aria-hidden="true"></i>
                <span>Order Baru</span>
            </a>
        </div>
    </div>

    <x-sf.card title="Order Berjalan">
        @if($orders->isEmpty())
            <x-sf.empty-state icon="🧾" title="Belum ada order berjalan" description="Mulai order baru untuk pelanggan di outlet ini." />
        @else
            <div class="divide-y divide-gray-50">
                @foreach($orders as $order)
                    <a href="{{ route('pos.orders.show', $order) }}" class="flex items-center justify-between gap-3 px-3 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $order->order_number }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $order->order_type === \App\Modules\Pos\Models\PosOrder::TYPE_DINE_IN ? 'Dine-in' : 'Takeaway' }}
                                @if($order->table) &middot; Meja {{ $order->table->code }} @endif
                                &middot; {{ $order->items->count() }} item
                            </p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 shrink-0">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
