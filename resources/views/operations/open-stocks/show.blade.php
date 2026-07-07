@extends('layouts.app')

@section('title', 'Detail Open Stock')

@section('topbar')
<x-sf.page-header
    title="{{ $openStock->item?->name ?? 'Open Stock' }}"
    subtitle="{{ $openStock->business_date->format('d M Y') }} · {{ $openStock->targetLabel() }}"
    back="{{ route('operations.open-stocks.index') }}"
>
    <x-slot:actions>
        @php
            $badgeClass = match($openStock->status) {
                'POSTED' => 'badge-posted',
                'VOID'   => 'badge-void',
                default  => 'badge-draft',
            };
        @endphp
        <span class="{{ $badgeClass }}">{{ $openStock->status }}</span>
    </x-slot:actions>
</x-sf.page-header>
@endsection

@section('content')
<div class="px-4 pt-4 pb-4 lg:px-6 lg:pb-8 space-y-4"
     @if($openStock->status === 'POSTED' && auth()->user()->can('post_open_stock'))
     x-data="{ voidModal: false }"
     @endif
>

    {{-- ══ INFO STOK ══ --}}
    <x-sf.card title="Informasi Stok">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <dt class="text-gray-500">Item</dt>
            <dd class="text-right font-semibold text-gray-900">{{ $openStock->item?->name ?? '—' }}</dd>

            <dt class="text-gray-500">SKU</dt>
            <dd class="text-right font-mono text-xs text-gray-600">{{ $openStock->item?->canonical_sku ?? '—' }}</dd>

            <dt class="text-gray-500">Sat. Inventory</dt>
            <dd class="text-right font-medium text-gray-700">{{ $openStock->item?->inventoryUnit?->code ?? '—' }}</dd>

            <dt class="text-gray-500">Sat. Dasar</dt>
            <dd class="text-right font-medium text-gray-700">
                {{ $openStock->item?->baseUnit?->code ?? $openStock->item?->inventoryUnit?->code ?? '—' }}
            </dd>

            <dt class="text-gray-500 col-span-2 border-t border-gray-50 pt-3 mt-1">Qty Input</dt>

            <dt class="text-gray-400 pl-2">Utuh</dt>
            <dd class="text-right">
                <span class="font-bold text-gray-900 text-lg">
                    {{ rtrim(rtrim((string)$openStock->qty_whole, '0'), '.') ?: '0' }}
                </span>
                <span class="text-xs text-gray-400 ml-1">{{ $openStock->item?->inventoryUnit?->code }}</span>
            </dd>

            <dt class="text-gray-400 pl-2">Ecer</dt>
            <dd class="text-right">
                <span class="font-bold text-gray-900 text-lg">
                    {{ rtrim(rtrim((string)$openStock->qty_loose, '0'), '.') ?: '0' }}
                </span>
                <span class="text-xs text-gray-400 ml-1">
                    {{ $openStock->item?->baseUnit?->code ?? $openStock->item?->inventoryUnit?->code }}
                </span>
            </dd>

            <dt class="text-gray-500 pt-2 border-t border-gray-50">Total (base)</dt>
            <dd class="text-right font-bold text-primary-800 text-lg pt-2 border-t border-gray-50">
                {{ rtrim(rtrim((string)$openStock->qty_in_base_unit, '0'), '.') ?: '0' }}
                <span class="text-xs text-gray-400 font-normal ml-1">{{ $openStock->unit?->code }}</span>
            </dd>

            @if($openStock->cost_per_unit)
                <dt class="text-gray-500">HPP / Unit</dt>
                <dd class="text-right font-medium text-gray-700">
                    Rp {{ number_format($openStock->cost_per_unit, 0, ',', '.') }}
                </dd>
            @endif
        </dl>
    </x-sf.card>

    {{-- ══ INFO TRANSAKSI ══ --}}
    <x-sf.card title="Info Transaksi">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <dt class="text-gray-500">Outlet</dt>
            <dd class="text-right font-medium text-gray-700">{{ $openStock->outlet?->name ?? '—' }}</dd>

            <dt class="text-gray-500">Target</dt>
            <dd class="text-right font-medium text-gray-700">{{ $openStock->targetLabel() }}</dd>

            <dt class="text-gray-500">Tanggal Bisnis</dt>
            <dd class="text-right font-medium text-gray-700">{{ $openStock->business_date->format('d M Y') }}</dd>

            <dt class="text-gray-500">Status</dt>
            <dd class="text-right">
                @php
                    $badgeClass = match($openStock->status) {
                        'POSTED' => 'badge-posted',
                        'VOID'   => 'badge-void',
                        default  => 'badge-draft',
                    };
                @endphp
                <span class="{{ $badgeClass }}">{{ $openStock->status }}</span>
            </dd>

            <dt class="text-gray-500">Dibuat oleh</dt>
            <dd class="text-right font-medium text-gray-700">{{ $openStock->createdBy?->name ?? '—' }}</dd>

            <dt class="text-gray-500">Dibuat pada</dt>
            <dd class="text-right text-gray-600">{{ $openStock->created_at->format('d M Y H:i') }}</dd>

            @if($openStock->posted_at)
                <dt class="text-gray-500 pt-2 border-t border-gray-50">Diposting oleh</dt>
                <dd class="text-right font-medium text-gray-700 pt-2 border-t border-gray-50">{{ $openStock->postedBy?->name ?? '—' }}</dd>

                <dt class="text-gray-500">Diposting pada</dt>
                <dd class="text-right text-gray-600">{{ $openStock->posted_at->format('d M Y H:i') }}</dd>
            @endif

            @if($openStock->voided_at)
                <dt class="text-red-400 pt-2 border-t border-red-50">Di-void oleh</dt>
                <dd class="text-right font-medium text-red-600 pt-2 border-t border-red-50">{{ $openStock->voidedBy?->name ?? '—' }}</dd>

                <dt class="text-red-400">Di-void pada</dt>
                <dd class="text-right text-red-500">{{ $openStock->voided_at->format('d M Y H:i') }}</dd>

                <dt class="text-red-400">Alasan</dt>
                <dd class="text-right text-red-500 col-span-1">{{ $openStock->void_reason }}</dd>
            @endif
        </dl>

        @if($openStock->notes)
            <div class="mt-3 pt-3 border-t border-gray-50">
                <p class="text-xs text-gray-500 mb-1">Catatan</p>
                <p class="text-sm text-gray-700">{{ $openStock->notes }}</p>
            </div>
        @endif
    </x-sf.card>

    {{-- ══ ACTION BUTTONS ══ --}}
    @if($openStock->status === 'DRAFT')
        <div class="flex flex-wrap gap-2">
            @can('input_open_stock')
                <a href="{{ route('operations.open-stocks.edit', $openStock) }}"
                   class="sf-btn-secondary">
                    Edit Draft
                </a>

                <form method="POST" action="{{ route('operations.open-stocks.destroy', $openStock) }}"
                      onsubmit="return confirm('Yakin hapus draft ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sf-btn-danger">Hapus Draft</button>
                </form>
            @endcan

            @can('post_open_stock')
                <form method="POST" action="{{ route('operations.open-stocks.post', $openStock) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="sf-btn-primary w-full">
                        ✅ Post ke Ledger
                    </button>
                </form>
            @endcan
        </div>
    @endif

    {{-- ══ VOID BUTTON (POSTED only) ══ --}}
    @if($openStock->status === 'POSTED')
        @can('post_open_stock')
            <div>
                <button type="button"
                        @click="voidModal = true"
                        class="sf-btn-danger w-full">
                    🚫 Void / Batalkan Open Stock Ini
                </button>
            </div>

            {{-- Void Confirmation Modal --}}
            <div x-show="voidModal"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-4 bg-black/40"
                 @keydown.escape.window="voidModal = false">

                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 md:translate-y-0 md:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
                     @click.stop>

                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-red-100 flex items-center justify-center shrink-0 text-2xl">🚫</div>
                            <div class="min-w-0">
                                <h3 class="font-heading font-bold text-gray-900 text-lg">Void Open Stock?</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    Ini akan membuat entry <strong>VOID_REVERSAL</strong> di ledger stok
                                    untuk item <strong>{{ $openStock->item?->name }}</strong>.
                                    Tindakan ini <strong class="text-red-600">tidak bisa dibatalkan</strong>.
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('operations.open-stocks.void', $openStock) }}"
                              class="mt-5 space-y-4">
                            @csrf

                            <x-sf.form-group label="Alasan Void" for="reason" :required="true"
                                hint="Minimal 5 karakter. Jelaskan mengapa Open Stock ini dibatalkan.">
                                <textarea
                                    id="reason"
                                    name="reason"
                                    rows="3"
                                    required
                                    minlength="5"
                                    placeholder="Contoh: Salah input qty, item duplikat, dsb."
                                    class="sf-input resize-none"
                                >{{ old('reason') }}</textarea>
                            </x-sf.form-group>

                            @if($errors->has('reason'))
                                <p class="text-xs text-red-500">{{ $errors->first('reason') }}</p>
                            @endif

                            <div class="flex gap-3 pt-1">
                                <button type="button"
                                        @click="voidModal = false"
                                        class="sf-btn-secondary flex-1">
                                    Batal
                                </button>
                                <button type="submit"
                                        class="sf-btn-danger flex-1">
                                    🚫 Ya, Void
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endif

</div>
@endsection

@if($openStock->status === 'POSTED' && auth()->user()->can('post_open_stock'))
    @if($errors->has('reason'))
        @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                // Auto-open void modal if reason has error
                document.addEventListener('DOMContentLoaded', () => {
                    const comp = document.querySelector('[x-data]');
                    if (comp && comp.__x) {
                        comp.__x.$data.voidModal = true;
                    }
                });
            });
        </script>
        @endpush
    @endif
@endif
