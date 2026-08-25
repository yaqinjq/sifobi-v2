@extends('layouts.public')

@section('title', 'Pesan Menu')
@section('subtitle', 'Meja ' . $table->code)

@section('content')
<div class="px-4 py-5 max-w-lg mx-auto w-full space-y-5">
    <x-sf.card title="Menu">
        <div class="divide-y divide-gray-50">
            @forelse($menus as $menu)
                <form method="POST" action="{{ $addItemUrl }}" class="flex items-center justify-between gap-3 py-3">
                    @csrf
                    <input type="hidden" name="menu_id" value="{{ $menu->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $menu->name }}</p>
                        <p class="text-xs text-gray-500">Rp {{ number_format((float) $menu->selling_price, 0, ',', '.') }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <input type="number" name="qty" value="1" min="0.01" step="1" class="sf-input text-sm w-16">
                        <button type="submit" class="sf-btn-primary text-sm px-3 py-2">Tambah</button>
                    </div>
                </form>
            @empty
                <p class="text-sm text-gray-400 py-4 text-center">Belum ada menu tersedia.</p>
            @endforelse
        </div>
    </x-sf.card>

    <x-sf.card title="Pesanan Anda">
        @if($order->items->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada pesanan. Pilih menu di atas.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($order->items as $item)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $item->item_name }}</p>
                            <p class="text-xs text-gray-500">{{ (float) $item->qty }} x Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-sm font-semibold text-gray-900">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                            <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('public.pos.items.destroy', ['table' => $table->id, 'item' => $item->id]) }}" onsubmit="return confirm('Hapus item ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <i class="ti ti-trash text-lg" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between font-bold text-gray-900 text-base pt-3 mt-3 border-t border-gray-100">
                <span>Total</span>
                <span>Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span>
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Member">
        @if($order->member)
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 truncate">Halo, {{ $order->member->name }}!</p>
                    <p class="text-xs text-gray-500">{{ $order->member->phone }}</p>
                </div>
                <span class="text-sm font-semibold text-primary-700 shrink-0">{{ number_format((float) $order->member->points_balance, 0, ',', '.') }} poin</span>
            </div>
        @else
            <form method="POST" action="{{ $memberUrl }}" class="space-y-2">
                @csrf
                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="No. HP" class="sf-input text-sm w-full" required>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Nama (kalau belum jadi member)" class="sf-input text-sm w-full">
                <button type="submit" class="sf-btn-secondary w-full min-h-11 text-sm">Jadi Member / Masuk</button>
            </form>
        @endif
    </x-sf.card>

    <p class="text-xs text-gray-400 text-center px-4">
        Pesanan Anda akan diproses oleh staff. Pembayaran dilakukan di kasir.
    </p>
</div>
@endsection
