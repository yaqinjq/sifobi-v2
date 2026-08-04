@extends('layouts.app')

@section('title', 'PO #'.$purchaseOrder->po_number)

@section('topbar')
<x-sf.page-header
    title="PO #{{ $purchaseOrder->po_number }}"
    subtitle="{{ $purchaseOrder->typeLabel() }}"
    back="{{ route('procurement.purchase-orders.index') }}"
>
    <x-slot:actions>
        <span class="{{ $purchaseOrder->statusBadgeClass() }}">{{ $purchaseOrder->statusLabel() }}</span>
    </x-slot:actions>
</x-sf.page-header>
@endsection

@section('content')
<div class="px-4 pt-4 pb-24 lg:px-6 lg:pb-8 space-y-4 max-w-5xl mx-auto w-full"
     x-data="{ rejectModal: false }">

    {{-- ── ALERT SUCCESS ── --}}
    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- ── INFO PO ── --}}
    <x-sf.card title="Informasi PO">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <dt class="text-gray-500">Nomor PO</dt>
            <dd class="text-right font-mono font-semibold text-gray-900">{{ $purchaseOrder->po_number }}</dd>

            <dt class="text-gray-500">Tujuan</dt>
            <dd class="text-right font-medium text-gray-700">{{ $purchaseOrder->typeLabel() }}</dd>

            <dt class="text-gray-500">Outlet</dt>
            <dd class="text-right font-medium text-gray-700">{{ $purchaseOrder->outlet?->name ?? '—' }}</dd>

            @if($purchaseOrder->department)
                <dt class="text-gray-500">Departemen</dt>
                <dd class="text-right font-medium text-gray-700">{{ $purchaseOrder->department->name }}</dd>
            @endif

            @if($purchaseOrder->needed_at)
                <dt class="text-gray-500">Dibutuhkan</dt>
                <dd class="text-right font-medium text-gray-700">{{ $purchaseOrder->needed_at->format('d M Y') }}</dd>
            @endif

            <dt class="text-gray-500">Status</dt>
            <dd class="text-right">
                <span class="{{ $purchaseOrder->statusBadgeClass() }}">{{ $purchaseOrder->statusLabel() }}</span>
            </dd>

            <dt class="text-gray-500">Dibuat oleh</dt>
            <dd class="text-right text-gray-600">{{ $purchaseOrder->requestedBy?->name ?? '—' }}</dd>

            <dt class="text-gray-500">Dibuat pada</dt>
            <dd class="text-right text-gray-500 text-xs">{{ $purchaseOrder->created_at->format('d M Y H:i') }}</dd>

            @if($purchaseOrder->submitted_at)
                <dt class="text-gray-500 border-t border-gray-50 pt-2">Diajukan oleh</dt>
                <dd class="text-right text-gray-600 border-t border-gray-50 pt-2">{{ $purchaseOrder->submittedBy?->name ?? '—' }}</dd>
                <dt class="text-gray-500">Tanggal diajukan</dt>
                <dd class="text-right text-gray-500 text-xs">{{ $purchaseOrder->submitted_at->format('d M Y H:i') }}</dd>
            @endif

            @if($purchaseOrder->approved_at)
                <dt class="text-gray-500 border-t border-gray-50 pt-2">Disetujui oleh</dt>
                <dd class="text-right text-gray-600 border-t border-gray-50 pt-2">{{ $purchaseOrder->approvedBy?->name ?? '—' }}</dd>
                <dt class="text-gray-500">Tanggal disetujui</dt>
                <dd class="text-right text-gray-500 text-xs">{{ $purchaseOrder->approved_at->format('d M Y H:i') }}</dd>
            @endif

            @if($purchaseOrder->rejected_at)
                <dt class="text-red-400 border-t border-red-50 pt-2">Ditolak oleh</dt>
                <dd class="text-right text-red-600 border-t border-red-50 pt-2">{{ $purchaseOrder->rejectedBy?->name ?? '—' }}</dd>
                <dt class="text-red-400">Alasan penolakan</dt>
                <dd class="text-right text-red-500 text-xs col-span-1">{{ $purchaseOrder->rejection_notes }}</dd>
            @endif

            @if($purchaseOrder->sent_at)
                <dt class="text-gray-500 border-t border-gray-50 pt-2">Dikirim oleh</dt>
                <dd class="text-right text-gray-600 border-t border-gray-50 pt-2">{{ $purchaseOrder->sentBy?->name ?? '—' }}</dd>
                <dt class="text-gray-500">Tanggal dikirim</dt>
                <dd class="text-right text-gray-500 text-xs">{{ $purchaseOrder->sent_at->format('d M Y H:i') }}</dd>
            @endif

            @php
                $isIntegrated = in_array($purchaseOrder->po_type, [
                    \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN,
                    \App\Modules\Procurement\Models\PurchaseOrder::TYPE_OCIA_ROASTERY,
                ]);
                $integrationTarget = $purchaseOrder->po_type === \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN ? 'Wipro' : 'OCIA';
            @endphp
            @if($isIntegrated && $purchaseOrder->sent_at)
                <dt class="text-gray-500 border-t border-gray-50 pt-2">Status ke {{ $integrationTarget }}</dt>
                <dd class="text-right border-t border-gray-50 pt-2">
                    @if($purchaseOrder->external_sync_error)
                        <span class="badge-void">Gagal kirim</span>
                    @elseif($purchaseOrder->external_synced_at)
                        <span class="badge-posted">Diterima {{ $integrationTarget }}</span>
                    @else
                        <span class="badge-pending">Menunggu</span>
                    @endif
                </dd>
                @if($purchaseOrder->external_reference)
                    <dt class="text-gray-500">No. Order {{ $integrationTarget }}</dt>
                    <dd class="text-right font-mono text-gray-600 text-xs">{{ $purchaseOrder->external_reference }}</dd>
                @endif
                @if($purchaseOrder->shipments->isNotEmpty())
                    <dt class="text-gray-500 border-t border-gray-50 pt-2">Pengiriman (DO)</dt>
                    <dd class="text-right border-t border-gray-50 pt-2">
                        <span class="badge-info">{{ $purchaseOrder->shipments->count() }} DO dikirim</span>
                        <span class="text-xs text-gray-400 block mt-0.5">
                            {{ $purchaseOrder->shipments->where('is_confirmed', true)->count() }} dikonfirmasi
                        </span>
                    </dd>
                @elseif($purchaseOrder->po_type === \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN && $purchaseOrder->wipro_shipped_at)
                    <dt class="text-gray-500 border-t border-gray-50 pt-2">Dikirim oleh Wipro</dt>
                    <dd class="text-right border-t border-gray-50 pt-2">
                        <span class="badge-posted">{{ $purchaseOrder->wipro_shipped_at->format('d M Y H:i') }}</span>
                        @if($purchaseOrder->wipro_dispatch_number)
                            <p class="text-xs text-gray-500 mt-1 font-mono">{{ $purchaseOrder->wipro_dispatch_number }}</p>
                        @endif
                    </dd>
                @endif
            @endif

            @if($purchaseOrder->closed_at)
                <dt class="text-gray-500 border-t border-gray-50 pt-2">Diselesaikan oleh</dt>
                <dd class="text-right text-gray-600 border-t border-gray-50 pt-2">{{ $purchaseOrder->closedBy?->name ?? '—' }}</dd>
                <dt class="text-gray-500">Tanggal selesai</dt>
                <dd class="text-right text-gray-500 text-xs">{{ $purchaseOrder->closed_at->format('d M Y H:i') }}</dd>
            @endif
        </dl>

        @if($purchaseOrder->notes)
            <div class="mt-3 pt-3 border-t border-gray-50">
                <p class="text-xs text-gray-400 mb-1">Catatan</p>
                <p class="text-sm text-gray-700">{{ $purchaseOrder->notes }}</p>
            </div>
        @endif
    </x-sf.card>

    {{-- ── DAFTAR ITEM ── --}}
    <x-sf.card>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h3 class="font-heading font-semibold text-gray-900 text-sm">Daftar Item</h3>
                <span class="text-xs text-gray-400">{{ $purchaseOrder->items->count() }} item</span>
            </div>
        </x-slot:header>

        @if($purchaseOrder->items->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-sm">Belum ada item ditambahkan</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($purchaseOrder->items as $poItem)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                {{ $poItem->item?->name ?? '—' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-medium text-gray-700">
                                    {{ rtrim(rtrim((string) $poItem->qty_ordered, '0'), '.') }}
                                    {{ $poItem->unit?->code ?? '—' }}
                                </span>
                                @if($poItem->unit_cost)
                                    &middot; Rp {{ number_format((float) $poItem->unit_cost, 0, ',', '.') }}/unit
                                    &middot; <span class="text-primary-700 font-medium">Rp {{ number_format((float) $poItem->subtotal(), 0, ',', '.') }}</span>
                                @endif
                            </p>
                            @if($poItem->notes)
                                <p class="text-xs text-gray-400 mt-0.5 italic">{{ $poItem->notes }}</p>
                            @endif
                        </div>
                        @if($purchaseOrder->canEdit())
                            @can('create_po')
                                <form method="POST"
                                      action="{{ route('procurement.purchase-orders.items.destroy', [$purchaseOrder, $poItem]) }}"
                                      onsubmit="return confirm('Hapus item ini dari PO?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-400 hover:text-red-600 transition-colors p-1 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Form Tambah Item (Draft only) --}}
        @if($purchaseOrder->canEdit())
            @can('create_po')
                <div class="border-t border-gray-100 px-4 py-4 mt-2 bg-gray-50/50 rounded-b-xl">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Tambah Item</p>
                    <form method="POST"
                          action="{{ route('procurement.purchase-orders.items.store', $purchaseOrder) }}"
                          class="space-y-3"
                          x-data="{
                              outletId: {{ $purchaseOrder->outlet_id ?? 'null' }},
                              suggestion: null,
                              loading: false,
                              async fetchSuggestion(itemId) {
                                  this.suggestion = null;
                                  if (!itemId || !this.outletId) return;
                                  this.loading = true;
                                  try {
                                      const res = await fetch(`{{ route('api.stock-suggestion') }}?item_id=${itemId}&outlet_id=${this.outletId}`, {
                                          headers: { 'Accept': 'application/json' },
                                      });
                                      if (res.ok) this.suggestion = await res.json();
                                  } catch (e) { /* saran opsional, abaikan jika gagal */ }
                                  this.loading = false;
                              },
                              applySuggestion() {
                                  if (this.suggestion) {
                                      this.$refs.qtyInput.value = this.suggestion.recommended_order;
                                  }
                              },
                          }">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item <span class="text-red-400">*</span></label>
                                <select name="item_id" class="sf-input text-sm" required
                                        @change="fetchSuggestion($event.target.value)">
                                    <option value="">-- Pilih item --</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                            {{ $item->name }}
                                            @if($item->canonical_sku) ({{ $item->canonical_sku }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Satuan <span class="text-red-400">*</span></label>
                                <select name="unit_id" class="sf-input text-sm" required>
                                    <option value="">-- Pilih satuan --</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>
                                            {{ $unit->name }} ({{ $unit->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div x-show="loading" class="text-xs text-gray-400">Memuat saran jumlah order&hellip;</div>
                        <div x-show="suggestion && !loading" x-cloak
                             class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 flex items-center justify-between gap-2">
                            <p class="text-xs text-blue-700">
                                Saran: <span class="font-semibold" x-text="suggestion?.recommended_order"></span>
                                <span x-text="suggestion?.unit_abbreviation"></span>
                                <template x-if="suggestion?.days_remaining !== null">
                                    <span> &middot; sisa stok ~<span x-text="suggestion?.days_remaining"></span> hari</span>
                                </template>
                                <template x-if="suggestion?.is_critical">
                                    <span class="font-semibold text-red-600"> &middot; kritis</span>
                                </template>
                            </p>
                            <button type="button" @click="applySuggestion()"
                                    class="text-xs font-semibold text-blue-700 shrink-0 underline">
                                Pakai saran
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Jumlah <span class="text-red-400">*</span></label>
                                <input type="number" name="qty_ordered" step="0.001" min="0.001"
                                       x-ref="qtyInput"
                                       value="{{ old('qty_ordered') }}"
                                       placeholder="0" class="sf-input text-sm" required />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Harga / Unit (opsional)</label>
                                <input type="number" name="unit_cost" step="1" min="0"
                                       value="{{ old('unit_cost') }}"
                                       placeholder="Rp 0" class="sf-input text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Catatan item</label>
                            <input type="text" name="notes" value="{{ old('notes') }}"
                                   placeholder="Spesifikasi, merek, dll." class="sf-input text-sm" />
                        </div>

                        <button type="submit" class="sf-btn-secondary w-full text-sm">
                            + Tambah Item
                        </button>
                    </form>
                </div>
            @endcan
        @endif
    </x-sf.card>

    {{-- ── PENGIRIMAN / DO ── --}}
    @if($purchaseOrder->shipments->isNotEmpty())
        <x-sf.card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <h3 class="font-heading font-semibold text-gray-900 text-sm">Pengiriman dari Vendor (DO)</h3>
                    <span class="text-xs text-gray-400">{{ $purchaseOrder->shipments->count() }} pengiriman</span>
                </div>
            </x-slot:header>
            <div class="divide-y divide-gray-50">
                @foreach($purchaseOrder->shipments as $i => $shipment)
                    @php
                        $qrPayload = json_encode([
                            'app'   => 'SIFOBI',
                            'type'  => 'shipment',
                            'v'     => 1,
                            'sid'   => $shipment->id,
                            'po_id' => $purchaseOrder->id,
                            'po'    => $purchaseOrder->po_number,
                            'do'    => $shipment->do_number,
                            'inv'   => $shipment->invoice_number,
                            'items' => $purchaseOrder->items->map(fn($item) => [
                                'iid' => $item->item_id,
                                'uid' => $item->unit_id,
                                'qty' => (float) $item->qty_ordered,
                            ])->values()->all(),
                        ], JSON_UNESCAPED_UNICODE);
                    @endphp
                    <div class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800">
                                    Box #{{ $i + 1 }}
                                    @if($shipment->do_number)
                                        &mdash; <span class="font-mono">{{ $shipment->do_number }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Invoice: <span class="font-mono">{{ $shipment->invoice_number ?: '—' }}</span>
                                    &middot; Dikirim: {{ $shipment->shipped_at?->format('d M Y H:i') ?: '—' }}
                                </p>
                                @if($shipment->goodsReceipt)
                                    <p class="text-xs text-green-700 mt-0.5">
                                        Penerimaan:
                                        <a href="{{ route('receiving.goods-receipts.show', $shipment->goodsReceipt) }}"
                                           class="underline font-mono">{{ $shipment->goodsReceipt->code }}</a>
                                    </p>
                                @endif
                                @if($shipment->notes)
                                    <p class="text-xs text-gray-400 mt-0.5 italic">{{ $shipment->notes }}</p>
                                @endif

                                {{-- QR Code per box/shipment --}}
                                <div class="mt-2">
                                    <button type="button"
                                            data-qr-target="shipment-qr-{{ $shipment->id }}"
                                            data-qr-payload="{{ htmlspecialchars($qrPayload) }}"
                                            onclick="toggleShipmentQr(this)"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-700 hover:text-primary-900 border border-primary-200 rounded-lg px-2.5 py-1.5 hover:bg-primary-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                        </svg>
                                        Tampilkan QR Box
                                    </button>
                                    <div id="shipment-qr-{{ $shipment->id }}" class="hidden mt-2">
                                        <canvas class="mx-auto rounded-xl border border-gray-100 p-2" width="200" height="200"></canvas>
                                        <p class="text-xs text-center text-gray-400 mt-1">Scan dengan kamera saat penerimaan barang</p>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                @if($shipment->is_confirmed)
                                    <span class="badge-posted text-xs">Diterima</span>
                                    @if($shipment->confirmed_at)
                                        <p class="text-xs text-gray-400 text-right mt-0.5">{{ $shipment->confirmed_at->format('d M Y') }}</p>
                                    @endif
                                @else
                                    <span class="badge-pending text-xs">Belum Diterima</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    {{-- ── PENERIMAAN BARANG TERKAIT ── --}}
    @if($purchaseOrder->goodsReceipts->isNotEmpty())
        <x-sf.card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <h3 class="font-heading font-semibold text-gray-900 text-sm">Penerimaan Barang (GR)</h3>
                    <span class="text-xs text-gray-400">{{ $purchaseOrder->goodsReceipts->count() }} GR</span>
                </div>
            </x-slot:header>
            <div class="divide-y divide-gray-50">
                @foreach($purchaseOrder->goodsReceipts as $gr)
                    <a href="{{ route('receiving.goods-receipts.show', $gr) }}"
                       class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold font-mono text-gray-800">{{ $gr->code }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $gr->receipt_date?->format('d M Y') ?: '—' }}
                                &middot; SJ: {{ $gr->doc_number ?: '—' }}
                                &middot; Inv: {{ $gr->invoice_number ?: '—' }}
                            </p>
                        </div>
                        <span class="badge-{{ $gr->status === 'POSTED' ? 'posted' : ($gr->status === 'SUBMITTED' ? 'pending' : 'draft') }} text-xs shrink-0">
                            {{ $gr->status }}
                        </span>
                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    {{-- ── RIWAYAT APPROVAL ── --}}
    @if($purchaseOrder->approvalEvents->isNotEmpty())
        <x-sf.card title="Riwayat Persetujuan">
            <div class="divide-y divide-gray-50">
                @foreach($purchaseOrder->approvalEvents as $event)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800">{{ $event->eventLabel() }}</span>
                            <span class="text-xs text-gray-400">{{ $event->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            oleh {{ $event->actor?->name ?? '—' }}
                            @if($event->from_status)
                                &middot; {{ \App\Modules\Procurement\Models\PurchaseOrder::STATUS_LABELS[$event->from_status] ?? $event->from_status }}
                                &rarr;
                                {{ \App\Modules\Procurement\Models\PurchaseOrder::STATUS_LABELS[$event->to_status] ?? $event->to_status }}
                            @endif
                        </p>
                        @if($event->notes)
                            <p class="text-xs text-gray-400 mt-1 italic">{{ $event->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    {{-- ── ACTION BUTTONS ── --}}
    <div class="space-y-3">

        {{-- SUBMIT --}}
        @if($purchaseOrder->canSubmit())
            @can('create_po')
                <form method="POST" action="{{ route('procurement.purchase-orders.submit', $purchaseOrder) }}">
                    @csrf
                    <button type="submit"
                            @if($purchaseOrder->items->isEmpty())
                                disabled title="Tambahkan item terlebih dahulu"
                            @endif
                            class="sf-btn-primary w-full {{ $purchaseOrder->items->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}">
                        Ajukan untuk Persetujuan
                    </button>
                </form>
            @endcan
        @endif

        {{-- APPROVE --}}
        @if($purchaseOrder->canApprove())
            @can('approve_po')
                <form method="POST" action="{{ route('procurement.purchase-orders.approve', $purchaseOrder) }}">
                    @csrf
                    <button type="submit" class="sf-btn-primary w-full">
                        Setujui PO Ini
                    </button>
                </form>
            @endcan
        @endif

        {{-- SEND --}}
        @if($purchaseOrder->canSend())
            @can('approve_po')
                <form method="POST" action="{{ route('procurement.purchase-orders.send', $purchaseOrder) }}"
                      onsubmit="return confirm('Tandai PO ini sudah dikirim ke vendor?')">
                    @csrf
                    <button type="submit" class="sf-btn-primary w-full">
                        Tandai Terkirim ke Vendor
                    </button>
                </form>
            @endcan
        @endif

        {{-- CLOSE --}}
        @if($purchaseOrder->canClose())
            @can('approve_po')
                <form method="POST" action="{{ route('procurement.purchase-orders.close', $purchaseOrder) }}"
                      onsubmit="return confirm('Tandai PO ini selesai / barang sudah diterima?')">
                    @csrf
                    <button type="submit" class="sf-btn-secondary w-full">
                        Tandai Selesai
                    </button>
                </form>
            @endcan
        @endif

        {{-- INTEGRATION RESEND (Central Kitchen → Wipro / OCIA Roastery → OCIA) --}}
        @php
            $isSentIntegrated = $purchaseOrder->status === \App\Modules\Procurement\Models\PurchaseOrder::STATUS_SENT
                && in_array($purchaseOrder->po_type, [
                    \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN,
                    \App\Modules\Procurement\Models\PurchaseOrder::TYPE_OCIA_ROASTERY,
                ]);
            $resendTarget = $purchaseOrder->po_type === \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN ? 'Wipro' : 'OCIA';
        @endphp
        @if($isSentIntegrated)
            @can('approve_po')
                @if($purchaseOrder->external_sync_error)
                    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold mb-1">Gagal dikirim ke {{ $resendTarget }}</p>
                        <p class="text-xs">{{ $purchaseOrder->external_sync_error }}</p>
                    </div>
                    <form method="POST" action="{{ route('procurement.purchase-orders.resend', $purchaseOrder) }}">
                        @csrf
                        <button type="submit" class="sf-btn-secondary w-full">
                            Kirim Ulang ke {{ $resendTarget }}
                        </button>
                    </form>
                @elseif($purchaseOrder->external_synced_at)
                    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                        <p class="font-semibold">Terkirim ke {{ $resendTarget }}</p>
                        <p class="text-xs mt-0.5">
                            Ref: {{ $purchaseOrder->external_reference ?? '—' }}
                            &middot; {{ $purchaseOrder->external_synced_at->format('d M Y H:i') }}
                        </p>
                    </div>
                @endif
            @endcan
        @endif

        {{-- REJECT --}}
        @if($purchaseOrder->canReject())
            @can('approve_po')
                <button type="button"
                        @click="rejectModal = true"
                        class="sf-btn-danger w-full">
                    Tolak PO Ini
                </button>
            @endcan
        @endif

    </div>

    {{-- ── REJECT MODAL ── --}}
    @can('approve_po')
        @if($purchaseOrder->canReject())
            <div x-show="rejectModal"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-4 bg-black/40"
                 @keydown.escape.window="rejectModal = false">

                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 md:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
                     @click.stop>
                    <div class="p-6">
                        <h3 class="font-heading font-bold text-gray-900 text-lg mb-1">Tolak PO?</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            PO <strong>{{ $purchaseOrder->po_number }}</strong> akan ditolak.
                            Berikan alasan penolakan.
                        </p>

                        <form method="POST" action="{{ route('procurement.purchase-orders.reject', $purchaseOrder) }}"
                              class="space-y-4">
                            @csrf

                            <x-sf.form-group label="Alasan Penolakan" for="rejection_notes" :required="true">
                                <textarea id="rejection_notes" name="rejection_notes" rows="3"
                                          required minlength="5"
                                          placeholder="Jelaskan alasan penolakan..."
                                          class="sf-input resize-none">{{ old('rejection_notes') }}</textarea>
                            </x-sf.form-group>

                            @error('rejection_notes')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="rejectModal = false" class="sf-btn-secondary flex-1">
                                    Batal
                                </button>
                                <button type="submit" class="sf-btn-danger flex-1">
                                    Ya, Tolak
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endcan

</div>
@endsection

@if($purchaseOrder->canReject() && auth()->user()->can('approve_po') && $errors->has('rejection_notes'))
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.querySelector('[x-data]');
                if (el && el._x_dataStack) {
                    el._x_dataStack[0].rejectModal = true;
                }
            });
        });
    </script>
    @endpush
@endif

@if($purchaseOrder->shipments->isNotEmpty())
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
    <script>
    function toggleShipmentQr(btn) {
        const targetId = btn.getAttribute('data-qr-target');
        const wrapper = document.getElementById(targetId);
        if (!wrapper) return;

        if (wrapper.classList.contains('hidden')) {
            wrapper.classList.remove('hidden');
            const canvas = wrapper.querySelector('canvas');
            if (canvas && !canvas.dataset.rendered) {
                const payload = btn.getAttribute('data-qr-payload');
                // QRCode library may not be loaded yet if defer — wait for it
                const render = () => QRCode.toCanvas(canvas, payload, { width: 200, margin: 1, color: { dark: '#111827', light: '#ffffff' } });
                if (typeof QRCode !== 'undefined') {
                    render();
                } else {
                    const s = document.querySelector('script[src*="qrcode"]');
                    s && s.addEventListener('load', render);
                }
                canvas.dataset.rendered = '1';
            }
            btn.textContent = 'Sembunyikan QR';
        } else {
            wrapper.classList.add('hidden');
            btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg> Tampilkan QR Box`;
        }
    }
    </script>
    @endpush
@endif
