@extends('layouts.app')

@section('title', $order->order_number)

@php
    $canEdit = $order->canAddItem();
    $canCheckout = $order->canCheckout();
    $canVoid = $order->canVoid();
@endphp

@section('content')
<x-sf.page-header
    :title="$order->order_number"
    subtitle="{{ $order->order_type === \App\Modules\Pos\Models\PosOrder::TYPE_DINE_IN ? 'Dine-in' : 'Takeaway' }}{{ $order->table ? ' · Meja '.$order->table->code : '' }}"
    back="{{ route('pos.orders.index', ['outlet_id' => $order->outlet_id]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <div class="flex items-center justify-between">
        <span class="{{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span>
        @if($order->status === \App\Modules\Pos\Models\PosOrder::STATUS_PAID)
            <a href="{{ route('pos.orders.receipt', $order) }}" class="sf-btn-secondary inline-flex min-h-11 items-center gap-2">
                <i class="ti ti-receipt text-base" aria-hidden="true"></i>
                <span>Lihat Struk</span>
            </a>
        @endif
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Item Order">
        @if($order->items->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada item.</p>
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
                            @if($canEdit)
                                <form method="POST" action="{{ route('pos.orders.items.destroy', [$order, $item]) }}" onsubmit="return confirm('Hapus item ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700">
                                        <i class="ti ti-trash text-lg" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($canEdit)
            <form method="POST" action="{{ route('pos.orders.items.store', $order) }}" class="flex flex-wrap items-end gap-2 mt-4 pt-4 border-t border-gray-100">
                @csrf
                <div class="flex-1 min-w-[10rem]">
                    <select name="menu_id" class="sf-input text-sm" required>
                        <option value="">Pilih menu</option>
                        @foreach($menus as $menu)
                            <option value="{{ $menu->id }}">{{ $menu->name }} — Rp {{ number_format((float) $menu->selling_price, 0, ',', '.') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-20">
                    <input type="number" name="qty" value="1" min="0.01" step="0.01" class="sf-input text-sm" required>
                </div>
                <button type="submit" class="sf-btn-primary min-h-11 px-4">Tambah</button>
            </form>
        @endif
    </x-sf.card>

    <x-sf.card title="Total">
        <div class="space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span>Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Diskon</span>
                <span>- Rp {{ number_format((float) $order->discount_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Pajak</span>
                <span>Rp {{ number_format((float) $order->tax_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Service Charge</span>
                <span>Rp {{ number_format((float) $order->service_charge_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-bold text-gray-900 text-base pt-1.5 border-t border-gray-100">
                <span>Total</span>
                <span>Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($canCheckout)
            <form method="POST" action="{{ route('pos.orders.checkout', $order) }}" x-data class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-100">
                @csrf
                <div>
                    <label class="text-xs text-gray-500">Diskon (Rp)</label>
                    <input type="number" name="discount_amount" value="{{ (float) $order->discount_amount }}" min="0" step="1" class="sf-input text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Pajak (Rp)</label>
                    <input type="number" name="tax_amount" value="{{ (float) $order->tax_amount }}" min="0" step="1" class="sf-input text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Service (Rp)</label>
                    <input type="number" name="service_charge_amount" value="{{ (float) $order->service_charge_amount }}" min="0" step="1" class="sf-input text-sm">
                </div>
                <button type="submit" class="sf-btn-secondary col-span-3 min-h-11 mt-1">Hitung Ulang Total</button>
            </form>
        @endif
    </x-sf.card>

    @if($order->status === \App\Modules\Pos\Models\PosOrder::STATUS_OPEN && $order->items->isNotEmpty())
        @unless($hasOpenShift)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-center gap-2">
                <i class="ti ti-alert-triangle text-base shrink-0" aria-hidden="true"></i>
                <span>Belum ada shift kasir yang dibuka untuk outlet ini — buka shift dulu sebelum menerima pembayaran.
                    <a href="{{ route('pos.shifts.index', ['outlet_id' => $order->outlet_id]) }}" class="underline font-semibold">Buka shift</a>
                </span>
            </div>
        @endunless

        <x-sf.card title="Pembayaran">
            <p class="text-sm text-gray-600 mb-3">Sisa tagihan: <span class="font-semibold text-gray-900">Rp {{ number_format((float) $remainingDue, 0, ',', '.') }}</span></p>

            <form method="POST" action="{{ route('pos.orders.pay', $order) }}"
                  x-data="{ amount: {{ (float) $remainingDue }}, due: {{ (float) $remainingDue }} }"
                  class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-500">Metode</label>
                        <select name="method" class="sf-input text-sm" required>
                            @foreach(\App\Modules\Pos\Models\PosPayment::METHOD_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Jumlah Dibayar (Rp)</label>
                        <input type="number" name="amount" x-model.number="amount" min="0.01" step="1" class="sf-input text-sm" required>
                    </div>
                </div>
                <p class="text-xs text-gray-500" x-show="amount > due">Kembalian: Rp <span x-text="(amount - due).toLocaleString('id-ID')"></span></p>
                <input type="text" name="reference_no" placeholder="No. referensi (opsional, untuk QRIS/kartu)" class="sf-input text-sm">
                <button type="submit" class="sf-btn-primary w-full min-h-12">Bayar</button>
            </form>
        </x-sf.card>
    @endif

    @if($order->payments->isNotEmpty())
        <x-sf.card title="Riwayat Pembayaran">
            <div class="divide-y divide-gray-50">
                @foreach($order->payments as $payment)
                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="text-gray-600">{{ $payment->methodLabel() }} &middot; {{ $payment->paid_at->format('d/m/Y H:i') }}</span>
                        <span class="font-semibold text-gray-900">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    @can('void_pos_order')
        @if($canVoid)
            <form method="POST" action="{{ route('pos.orders.void', $order) }}" onsubmit="return confirm('Batalkan order ini? Meja akan kembali kosong.')">
                @csrf
                <button type="submit" class="sf-btn-danger w-full min-h-11">Batalkan Order</button>
            </form>
        @endif
    @endcan
</div>
@endsection
