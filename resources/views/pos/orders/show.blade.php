@extends('layouts.app')

@section('title', $order->order_number)

@php
    $canEdit = $order->canAddItem();
    $canCheckout = $order->canCheckout();
    $canVoid = $order->canVoid();
    $canSplitOrMerge = $order->canSplitOrMerge();
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
                    <div class="py-3" @if($canEdit && $canSplitOrMerge && $otherOpenOrders->isNotEmpty()) x-data="{ splitOpen: false }" @endif>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 truncate">{{ $item->item_name }}</p>
                                @if($canEdit)
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <form method="POST" action="{{ route('pos.orders.items.update', [$order, $item]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="qty" value="{{ bcsub((string) $item->qty, '1', 4) }}">
                                            <button type="submit" class="w-6 h-6 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 flex items-center justify-center leading-none">−</button>
                                        </form>
                                        <span class="text-sm font-medium text-gray-700 w-6 text-center">{{ (float) $item->qty }}</span>
                                        <form method="POST" action="{{ route('pos.orders.items.update', [$order, $item]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="qty" value="{{ bcadd((string) $item->qty, '1', 4) }}">
                                            <button type="submit" class="w-6 h-6 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 flex items-center justify-center leading-none">+</button>
                                        </form>
                                        <span class="text-xs text-gray-400 ml-1">x Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</span>
                                    </div>
                                @else
                                    <p class="text-xs text-gray-500">{{ (float) $item->qty }} x Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-sm font-semibold text-gray-900">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                                @if($canEdit && $canSplitOrMerge && $otherOpenOrders->isNotEmpty())
                                    <button type="button" @click="splitOpen = !splitOpen" class="text-gray-400 hover:text-primary-700">
                                        <i class="ti ti-arrows-split text-lg" aria-hidden="true"></i>
                                    </button>
                                @endif
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

                        @if($canEdit && $canSplitOrMerge && $otherOpenOrders->isNotEmpty())
                            <form x-show="splitOpen" x-cloak method="POST" action="{{ route('pos.orders.items.split', [$order, $item]) }}"
                                  class="flex flex-wrap items-end gap-2 mt-2 pl-1">
                                @csrf
                                <div class="w-20">
                                    <label class="text-xs text-gray-500">Qty pindah</label>
                                    <input type="number" name="qty" value="{{ (float) $item->qty }}" min="0.01" max="{{ (float) $item->qty }}" step="0.01" class="sf-input text-sm" required>
                                </div>
                                <div class="flex-1 min-w-[8rem]">
                                    <label class="text-xs text-gray-500">Pindah ke order</label>
                                    <select name="target_order_id" class="sf-input text-sm" required>
                                        @foreach($otherOpenOrders as $other)
                                            <option value="{{ $other->id }}">{{ $other->order_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="sf-btn-secondary min-h-11 px-3 text-sm">Split</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($canEdit)
            <div class="mt-4 pt-4 border-t border-gray-100"
                 x-data="{
                    search: '',
                    menus: @js($menus->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price' => (float) $m->selling_price])),
                    get filtered() {
                        const q = this.search.trim().toLowerCase();
                        return q === '' ? this.menus : this.menus.filter(m => m.name.toLowerCase().includes(q));
                    }
                 }">
                <div class="relative mb-3">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                    </div>
                    <input type="text" x-model="search" placeholder="Cari menu..." class="sf-input pl-9 text-sm w-full" autocomplete="off" spellcheck="false">
                </div>

                @if($menus->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">Belum ada menu tersedia. Tambah menu dulu di modul Menu &amp; Resep.</p>
                @else
                    <p class="text-sm text-gray-400 py-6 text-center" x-show="filtered.length === 0" x-cloak>Menu tidak ditemukan.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="menu in filtered" :key="menu.id">
                            <form method="POST" action="{{ route('pos.orders.items.store', $order) }}">
                                @csrf
                                <input type="hidden" name="menu_id" :value="menu.id">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="w-full h-full rounded-xl border border-gray-200 hover:border-primary-400 hover:bg-primary-50 px-3 py-3 text-left transition-colors">
                                    <p class="text-sm font-semibold text-gray-900 truncate" x-text="menu.name"></p>
                                    <p class="text-xs text-gray-500 mt-0.5" x-text="'Rp ' + menu.price.toLocaleString('id-ID')"></p>
                                </button>
                            </form>
                        </template>
                    </div>
                @endif
            </div>
        @endif
    </x-sf.card>

    @if($canSplitOrMerge && $otherOpenOrders->isNotEmpty())
        <x-sf.card title="Gabung dari Order Lain">
            <form method="POST" action="{{ route('pos.orders.merge', $order) }}"
                  onsubmit="return confirm('Semua item dari order yang dipilih akan dipindah ke order ini, lalu order tsb dibatalkan (digabung). Lanjutkan?')"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="flex-1 min-w-[10rem]">
                    <select name="source_order_id" class="sf-input text-sm" required>
                        @foreach($otherOpenOrders as $other)
                            <option value="{{ $other->id }}">{{ $other->order_number }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="sf-btn-secondary min-h-11 px-4">Gabung ke Sini</button>
            </form>
        </x-sf.card>
    @endif

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
