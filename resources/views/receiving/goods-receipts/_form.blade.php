@php
    $activeSource = old('source', $source ?: $receipt->source);
    $isEdit = $receipt->exists;
    $oldRows = old('items');

    if (is_array($oldRows)) {
        $rows = collect($oldRows)->values()->map(fn ($row) => [
            'item_id' => $row['item_id'] ?? '',
            'unit_id' => $row['unit_id'] ?? '',
            'qty_ordered' => $row['qty_ordered'] ?? '0',
            'qty_received' => $row['qty_received'] ?? '',
            'unit_price' => $row['unit_price'] ?? '0',
            'expired_date' => $row['expired_date'] ?? '',
            'batch_code' => $row['batch_code'] ?? '',
            'notes' => $row['notes'] ?? '',
            'variance_reason' => $row['variance_reason'] ?? '',
        ])->all();
    } elseif ($isEdit) {
        $rows = $receipt->items->map(fn ($item) => [
            'item_id' => (string) $item->item_id,
            'unit_id' => (string) $item->unit_id,
            'qty_ordered' => (string) $item->qty_ordered,
            'qty_received' => (string) $item->qty_received,
            'unit_price' => (string) ($item->unit_price ?? $item->unit_cost ?? 0),
            'expired_date' => optional($item->expired_date)->format('Y-m-d'),
            'batch_code' => $item->batch_code ?? '',
            'notes' => $item->notes ?? '',
            'variance_reason' => $item->variance_reason ?? '',
        ])->values()->all();
    } else {
        $rows = [[
            'item_id' => '',
            'unit_id' => '',
            'qty_ordered' => '0',
            'qty_received' => '',
            'unit_price' => '0',
            'expired_date' => '',
            'batch_code' => '',
            'notes' => '',
            'variance_reason' => '',
        ]];
    }

    $unitsForAlpine = $units->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => $unit->code.' - '.$unit->name,
        'abbreviation' => $unit->abbreviation,
    ])->values()->all();
@endphp

{{-- ── SCAN MODAL ── --}}
<div x-data
     x-show="$store.scanner.open"
     x-cloak
     class="fixed inset-0 z-50 flex flex-col bg-black"
     style="display:none;">
    <div class="flex items-center justify-between px-4 py-3 bg-gray-900 text-white">
        <div class="flex gap-2">
            <button type="button"
                    @click="$store.scanner.mode = 'qr'"
                    :class="$store.scanner.mode === 'qr' ? 'bg-primary-600 text-white' : 'bg-gray-700 text-gray-300'"
                    class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors">
                Scan QR Box
            </button>
            <button type="button"
                    @click="$store.scanner.mode = 'barcode'"
                    :class="$store.scanner.mode === 'barcode' ? 'bg-primary-600 text-white' : 'bg-gray-700 text-gray-300'"
                    class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors">
                Scan Barcode Item
            </button>
        </div>
        <button type="button" @click="$store.scanner.close()" class="text-gray-400 hover:text-white p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="flex-1 flex items-center justify-center bg-black relative">
        <div id="scanner-viewport" class="w-full max-w-sm aspect-square rounded-xl overflow-hidden"></div>
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="border-2 border-white/40 rounded-xl w-64 h-64">
                <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary-400 rounded-tl-lg"></div>
                <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary-400 rounded-tr-lg"></div>
                <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary-400 rounded-bl-lg"></div>
                <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary-400 rounded-br-lg"></div>
            </div>
        </div>
    </div>
    <div class="px-4 py-3 bg-gray-900 text-center text-sm text-gray-400">
        <p x-show="$store.scanner.mode === 'qr'">Arahkan kamera ke QR code yang ada di box/paket pengiriman</p>
        <p x-show="$store.scanner.mode === 'barcode'">Arahkan kamera ke barcode item untuk menambah ke daftar penerimaan</p>
        <p x-show="$store.scanner.scanResult" x-text="$store.scanner.scanResult" class="text-green-400 font-semibold mt-1"></p>
    </div>
</div>

<form method="POST"
      action="{{ $formAction }}"
      enctype="multipart/form-data"
      x-data="goodsReceiptForm({
          items: @js($itemsForAlpine),
          units: @js($unitsForAlpine),
          rows: @js($rows),
          allPoList: @js($shippedPos),
          initialPoId: @js(old('purchase_order_id', $receipt->purchase_order_id)),
          initialShipmentId: @js(old('purchase_order_shipment_id', $receipt->purchase_order_shipment_id ?? null))
      })"
      @submit="handleSubmit($event)"
      class="space-y-4">
    @csrf
    @if(($formMethod ?? 'POST') !== 'POST')
        @method($formMethod)
    @endif

    <input type="hidden" name="source" value="{{ $activeSource }}">

    {{-- Tombol Scan (muncul di PO source) --}}
    @if(in_array($activeSource, ['WIP_CENTRAL_KITCHEN', 'OCIA_PO']))
    <div class="flex justify-end">
        <button type="button"
                @click="$store.scanner.openWith($store.scanner.mode, $el.closest('form'))"
                class="inline-flex items-center gap-2 sf-btn-secondary min-h-11 px-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
            </svg>
            <span>Scan QR / Barcode</span>
        </button>
    </div>
    @endif

    <x-sf.card title="Informasi Dokumen">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="sf-label">Kode</label>
                <input type="text" value="{{ $codePreview }}" class="sf-input text-base min-h-11 bg-gray-50" readonly>
            </div>
            <div>
                <label class="sf-label">Outlet *</label>
                <select name="outlet_id" class="sf-input text-base min-h-11" required>
                    <option value="">Pilih outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $receipt->outlet_id ?: auth()->user()->outlet_id) === (string) $outlet->id)>
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
                @error('outlet_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="sf-label">Tanggal Terima *</label>
                <input type="date" name="receipt_date" value="{{ old('receipt_date', optional($receipt->receipt_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="sf-input text-base min-h-11" required>
                @error('receipt_date')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="sf-label">Nomor Dokumen/SJ</label>
                <input type="text" name="doc_number" value="{{ old('doc_number', $receipt->doc_number) }}" class="sf-input text-base min-h-11" maxlength="120">
            </div>
            <div>
                <label class="sf-label">Nomor Invoice</label>
                <input type="text" name="invoice_number" value="{{ old('invoice_number', $receipt->invoice_number) }}" class="sf-input text-base min-h-11" maxlength="120">
            </div>
            <div>
                <label class="sf-label">Foto Surat Jalan / SJ</label>
                <div class="flex gap-2">
                    <input type="file" name="photo_document" accept="image/png,image/jpeg,image/webp" class="sf-input text-base min-h-11 flex-1">
                    <button type="button"
                            @click="capturePhoto('photo_document')"
                            class="sf-btn-secondary min-h-11 px-3 shrink-0"
                            title="Foto dengan kamera">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
                @if($receipt->photo_document)
                    <p class="text-xs text-gray-500 mt-1">Foto lama tersimpan. Upload baru untuk mengganti.</p>
                @endif
            </div>
            <div>
                <label class="sf-label">Foto Invoice</label>
                <div class="flex gap-2">
                    <input type="file" name="photo_invoice" accept="image/png,image/jpeg,image/webp" class="sf-input text-base min-h-11 flex-1">
                    <button type="button"
                            @click="capturePhoto('photo_invoice')"
                            class="sf-btn-secondary min-h-11 px-3 shrink-0"
                            title="Foto dengan kamera">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
                @if($receipt->photo_invoice)
                    <p class="text-xs text-gray-500 mt-1">Foto invoice lama tersimpan. Upload baru untuk mengganti.</p>
                @endif
            </div>
        </div>
    </x-sf.card>

    <x-sf.card title="Detail Sumber">
        @if($activeSource === 'WIP_CENTRAL_KITCHEN' || $activeSource === 'OCIA_PO')
            @php
                $poType = $activeSource === 'WIP_CENTRAL_KITCHEN'
                    ? \App\Modules\Procurement\Models\PurchaseOrder::TYPE_CENTRAL_KITCHEN
                    : \App\Modules\Procurement\Models\PurchaseOrder::TYPE_OCIA_ROASTERY;
                $filteredPos = collect($shippedPos)->filter(fn($p) => $p['po_type'] === $poType)->values();
                $linkedPoId = old('purchase_order_id', $receipt->purchase_order_id);
            @endphp

            <input type="hidden" name="purchase_order_id" x-ref="poIdInput"
                   value="{{ $linkedPoId }}">
            <input type="hidden" name="purchase_order_shipment_id" x-ref="shipmentIdInput"
                   :value="selectedShipmentId">

            <label class="sf-label">
                Pilih PO {{ $activeSource === 'WIP_CENTRAL_KITCHEN' ? 'Central Kitchen (Wipro)' : 'OCIA' }}
                <span class="text-gray-400 font-normal">&mdash; hanya PO berstatus Dikirim Vendor atau Terkirim</span>
            </label>

            @if($filteredPos->isEmpty())
                <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                    Tidak ada PO dengan status "Dikirim Vendor" atau "Terkirim ke Vendor" saat ini.
                    Pastikan PO sudah disetujui dan dikirim ke vendor terlebih dahulu.
                </div>
            @else
                <select class="sf-input text-base min-h-11"
                        x-ref="poSelect"
                        @change="loadPoItems($event.target.value, @js($filteredPos->values()->all()), $refs.poIdInput)">
                    <option value="">— Pilih PO —</option>
                    @foreach($filteredPos as $p)
                        <option value="{{ $p['id'] }}" @selected((string)$linkedPoId === (string)$p['id'])>
                            {{ $p['po_number'] }}
                            &middot; {{ $p['outlet'] }}
                            @if($p['shipped_at']) &middot; Dikirim {{ $p['shipped_at'] }} @endif
                            &middot; {{ $p['status'] === 'SHIPPED' ? 'Dikirim Vendor' : 'Terkirim ke Vendor' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Pilih PO atau scan QR box untuk auto-isi form.</p>

                {{-- Shipment / DO picker --}}
                <div x-show="currentPoShipments.length > 0" x-cloak class="mt-3">
                    <label class="sf-label">
                        Pilih Pengiriman / Surat Jalan (DO)
                        <span class="text-gray-400 font-normal">&mdash; 1 GR per DO</span>
                    </label>
                    <select class="sf-input text-base min-h-11"
                            x-model="selectedShipmentId"
                            @change="applyShipmentDoc($event.target.value, currentPoShipments)">
                        <option value="">— Pilih DO/Pengiriman —</option>
                        <template x-for="s in currentPoShipments" :key="s.id">
                            <option :value="String(s.id)"
                                    :disabled="s.is_confirmed"
                                    x-text="(s.do_number ? 'DO: ' + s.do_number : 'Box tanpa no. DO')
                                        + (s.invoice_number ? '  ·  Inv: ' + s.invoice_number : '')
                                        + (s.shipped_at ? '  ·  ' + s.shipped_at : '')
                                        + (s.is_confirmed ? '  ✓ Sudah diterima' : '')">
                            </option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Pilih DO/box yang sedang Anda terima. Setiap DO jadi 1 GR terpisah.
                    </p>
                </div>
            @endif

        @elseif($activeSource === 'SUPPLIER_LUAR')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="sf-label">Supplier</label>
                    <select name="supplier_id" class="sf-input text-base min-h-11">
                        <option value="">Pilih supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $receipt->supplier_id) === (string) $supplier->id)>
                                {{ $supplier->code }} - {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Supplier Manual</label>
                    <input type="text" name="supplier_name" value="{{ old('supplier_name', $receipt->supplier_name) }}" class="sf-input text-base min-h-11" maxlength="150">
                </div>
            </div>
        @else
            <label class="sf-label">Nama Pengirim</label>
            <input type="text" name="supplier_name" value="{{ old('supplier_name', $receipt->supplier_name) }}" class="sf-input text-base min-h-11" maxlength="150" placeholder="Central Kitchen / Purchasing">
        @endif
    </x-sf.card>

    <x-sf.card title="Item Diterima" subtitle="Qty akan dikonversi ke satuan dasar item saat posting">
        @error('items')<p class="text-sm text-red-600 mb-3">{{ $message }}</p>@enderror

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="rounded-xl border border-gray-200 bg-white p-3 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-900">Baris <span x-text="index + 1"></span></p>
                        <button type="button" @click="removeRow(index)" class="sf-btn-secondary min-h-11 px-3" x-show="rows.length > 1">Hapus</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <div class="md:col-span-4">
                            <label class="sf-label">Item *</label>
                            <select :name="`items[${index}][item_id]`" x-model="row.item_id" @change="applyItem(row)" class="sf-input text-base min-h-11" required>
                                <option value="">Pilih item</option>
                                <template x-for="item in items" :key="item.id">
                                    <option :value="item.id" x-text="`${item.name} - ${item.sku}`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="sf-label">Satuan *</label>
                            <select :name="`items[${index}][unit_id]`" x-model="row.unit_id" class="sf-input text-base min-h-11" required>
                                <option value="">Satuan</option>
                                <template x-for="unit in units" :key="unit.id">
                                    <option :value="unit.id" x-text="unit.label"></option>
                                </template>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="sf-label">Qty PO</label>
                            <input type="text" inputmode="decimal" :name="`items[${index}][qty_ordered]`" x-model="row.qty_ordered" readonly class="sf-input text-base min-h-11 bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div class="md:col-span-2">
                            <label class="sf-label">Qty Terima *</label>
                            <input type="text" inputmode="decimal" :name="`items[${index}][qty_received]`" x-model="row.qty_received" class="sf-input text-base min-h-11" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="sf-label">Harga</label>
                            <input type="text" inputmode="decimal" :name="`items[${index}][unit_price]`" x-model="row.unit_price" class="sf-input text-base min-h-11">
                        </div>
                    </div>

                    {{-- Status kesesuaian qty vs PO — cuma bermakna kalau ada Qty PO --}}
                    <div x-show="parseFloat(row.qty_ordered) > 0" class="flex items-center gap-2">
                        <template x-if="rowVariance(row) === 0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 text-xs font-semibold px-2.5 py-1">
                                ✓ Sesuai PO
                            </span>
                        </template>
                        <template x-if="rowVariance(row) < 0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold px-2.5 py-1">
                                ⚠ Kurang <span x-text="formatQty(Math.abs(rowVariance(row)))"></span>
                            </span>
                        </template>
                        <template x-if="rowVariance(row) > 0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold px-2.5 py-1">
                                ⚠ Lebih <span x-text="formatQty(rowVariance(row))"></span>
                            </span>
                        </template>
                    </div>

                    {{-- Alasan selisih — wajib diisi kalau qty terima beda dari qty PO --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" x-show="rowVariance(row) !== 0">
                        <div>
                            <label class="sf-label">Alasan Selisih *</label>
                            <select :name="`items[${index}][variance_reason]`" x-model="row.variance_reason"
                                    :required="rowVariance(row) !== 0" class="sf-input text-base min-h-11">
                                <option value="">Pilih alasan</option>
                                @foreach(\App\Modules\Receiving\Models\GoodsReceiptItem::VARIANCE_REASONS as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="sf-label">Catatan Tambahan</label>
                            <input type="text" :name="`items[${index}][notes]`" x-model="row.notes" class="sf-input text-base min-h-11" placeholder="Opsional">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3" x-show="row.track_expiry">
                        <div>
                            <label class="sf-label">Expired Date</label>
                            <input type="date" :name="`items[${index}][expired_date]`" x-model="row.expired_date" class="sf-input text-base min-h-11">
                        </div>
                        <div>
                            <label class="sf-label">Batch Code</label>
                            <input type="text" :name="`items[${index}][batch_code]`" x-model="row.batch_code" class="sf-input text-base min-h-11">
                        </div>
                        {{-- Catatan Item dobel-fungsi sebagai catatan expiry kalau tidak ada selisih --}}
                        <div x-show="rowVariance(row) === 0">
                            <label class="sf-label">Catatan Item</label>
                            <input type="text" :name="`items[${index}][notes]`" x-model="row.notes" class="sf-input text-base min-h-11">
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-semibold text-gray-900" x-text="formatCurrency(rowTotal(row))"></span>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap gap-2 mt-4">
            <button type="button" @click="addRow()" class="sf-btn-secondary min-h-11 px-4">+ Tambah Baris</button>
            <button type="button"
                    @click="$store.scanner.openWith('barcode', $el.closest('form'))"
                    class="inline-flex items-center gap-2 sf-btn-secondary min-h-11 px-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                Scan Barcode Item
            </button>
        </div>
    </x-sf.card>

    <x-sf.card title="Catatan">
        <textarea name="notes" rows="3" class="sf-input text-base" placeholder="Catatan tambahan">{{ old('notes', $receipt->notes) }}</textarea>
    </x-sf.card>

    <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
        <div class="max-w-5xl mx-auto flex flex-col sm:flex-row gap-2 sm:justify-between sm:items-center">
            <p class="text-sm text-gray-600">Total: <span class="font-bold text-gray-900" x-text="formatCurrency(grandTotal())"></span></p>
            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('receiving.goods-receipts.index') }}" class="sf-btn-secondary min-h-11 px-4 text-center">Batal</a>
                <button type="submit" name="action" value="draft" class="sf-btn-secondary min-h-11 px-4">Simpan Draft</button>
                @can('submit_goods_receipt')
                    <button type="submit" name="action" value="submit" class="sf-btn-primary min-h-11 px-4">Submit Review</button>
                @endcan
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
<script>
// ── Alpine Store: scanner singleton ─────────────────────────────────────────
document.addEventListener('alpine:init', () => {
    Alpine.store('scanner', {
        open: false,
        mode: 'qr',
        scanResult: '',
        _scanner: null,
        _formEl: null,

        openWith(mode, formEl) {
            this.mode = mode || 'qr';
            this.scanResult = '';
            this._formEl = formEl;
            this.open = true;
            setTimeout(() => this._start(), 300);
        },

        close() {
            this.open = false;
            this._stop();
        },

        _stop() {
            if (this._scanner) {
                this._scanner.stop().catch(() => {});
                this._scanner = null;
            }
        },

        _start() {
            this._stop();
            const el = document.getElementById('scanner-viewport');
            if (!el) return;

            this._scanner = new Html5Qrcode('scanner-viewport');

            const fmts = this.mode === 'barcode'
                ? [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                ]
                : [Html5QrcodeSupportedFormats.QR_CODE];

            this._scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 260, height: 260 }, formatsToSupport: fmts },
                (text) => this._onSuccess(text),
                () => {}
            ).catch(err => {
                console.error('Camera error:', err);
                alert('Kamera tidak bisa diakses. Pastikan izin kamera diizinkan.');
                this.close();
            });
        },

        _onSuccess(text) {
            this.close();

            // Find the goodsReceiptForm Alpine component
            const formEl = this._formEl || document.querySelector('[x-data*="goodsReceiptForm"]');
            if (!formEl) return;
            const component = Alpine.$data(formEl);
            if (!component) return;

            // Try parse as SIFOBI shipment QR
            try {
                const data = JSON.parse(text);
                if (data.app === 'SIFOBI' && data.type === 'shipment') {
                    this.scanResult = 'QR Box terdeteksi: ' + (data.do || data.po || text.substring(0, 30));
                    component.applyShipmentQr(data);
                    return;
                }
            } catch (_) {}

            // Treat as item barcode
            this.scanResult = 'Barcode: ' + text;
            component.addItemByBarcode(text);
        },
    });
});

// ── goodsReceiptForm Alpine component ───────────────────────────────────────
function goodsReceiptForm(config) {
    const blankRow = () => ({
        key: `${Date.now()}-${Math.random()}`,
        item_id: '',
        unit_id: '',
        qty_ordered: '0',
        qty_received: '',
        unit_price: '0',
        expired_date: '',
        batch_code: '',
        notes: '',
        variance_reason: '',
        track_expiry: false,
    });

    const rows = (config.rows || []).map((row) => ({
        key: `${Date.now()}-${Math.random()}`,
        track_expiry: false,
        variance_reason: '',
        ...row,
    }));

    if (rows.length === 0) rows.push(blankRow());

    const allPoList = config.allPoList || [];
    const initialPoId = config.initialPoId ? String(config.initialPoId) : '';
    const initialShipmentId = config.initialShipmentId ? String(config.initialShipmentId) : '';
    const initialPo = initialPoId ? allPoList.find(p => String(p.id) === initialPoId) : null;

    return {
        items: config.items || [],
        units: config.units || [],
        rows,
        selectedShipmentId: initialShipmentId,
        currentPoShipments: initialPo ? (initialPo.shipments || []) : [],
        currentPoItems: initialPo ? (initialPo.items || []) : [],

        addRow() {
            this.rows.push(blankRow());
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
        },

        loadPoItems(poId, poList, hiddenInput) {
            if (hiddenInput) hiddenInput.value = poId || '';
            this.selectedShipmentId = '';
            if (!poId) { this.currentPoShipments = []; this.currentPoItems = []; return; }
            const po = poList.find(p => String(p.id) === String(poId));
            if (!po) { this.currentPoShipments = []; this.currentPoItems = []; return; }
            this.currentPoShipments = po.shipments || [];
            this.currentPoItems = po.items || [];
            const pending = this.currentPoShipments.filter(s => !s.is_confirmed);
            if (pending.length === 1) {
                this.selectedShipmentId = String(pending[0].id);
                this.applyShipmentDoc(this.selectedShipmentId, this.currentPoShipments);
            }
            if (!po.items || po.items.length === 0) return;
            this.rows = po.items.map(pi => {
                const item = this.items.find(i => Number(i.id) === Number(pi.item_id));
                return {
                    key: `${Date.now()}-${Math.random()}`,
                    item_id: String(pi.item_id),
                    unit_id: String(pi.unit_id),
                    qty_ordered: String(pi.qty_ordered),
                    qty_received: String(pi.qty_ordered),
                    unit_price: '0',
                    expired_date: '',
                    batch_code: '',
                    notes: '',
                    variance_reason: '',
                    track_expiry: item ? item.track_expiry === true : false,
                };
            });
        },

        applyShipmentDoc(shipmentId, shipments) {
            if (!shipmentId) return;
            const s = shipments.find(x => String(x.id) === String(shipmentId));
            if (!s) return;
            const docEl = document.querySelector('[name="doc_number"]');
            const invEl = document.querySelector('[name="invoice_number"]');
            if (docEl && !docEl.value && s.do_number) docEl.value = s.do_number;
            if (invEl && !invEl.value && s.invoice_number) invEl.value = s.invoice_number;
        },

        applyShipmentQr(data) {
            const po = allPoList.find(p => Number(p.id) === Number(data.po_id));
            if (!po) {
                alert('PO tidak ditemukan di daftar. Pastikan PO sudah berstatus Dikirim.');
                return;
            }
            // Update PO select dropdown
            const poSelectEl = this.$refs.poSelect;
            if (poSelectEl) poSelectEl.value = String(po.id);
            this.loadPoItems(String(po.id), allPoList, this.$refs.poIdInput);

            // Override shipment to the scanned one
            if (data.sid) {
                this.selectedShipmentId = String(data.sid);
            }

            // Fill doc + invoice from QR (override even if already set)
            this.$nextTick(() => {
                const docEl = document.querySelector('[name="doc_number"]');
                const invEl = document.querySelector('[name="invoice_number"]');
                if (docEl && data.do) docEl.value = data.do;
                if (invEl && data.inv) invEl.value = data.inv;
            });
        },

        addItemByBarcode(barcode) {
            const item = this.items.find(i => i.barcode === barcode);
            if (!item) {
                alert(`Barcode "${barcode}" tidak ditemukan di daftar item. Tambahkan barcode item di Master Data.`);
                return;
            }
            const existingRow = this.rows.find(r => String(r.item_id) === String(item.id));
            if (existingRow) {
                const current = parseFloat(existingRow.qty_received) || 0;
                existingRow.qty_received = String(current + 1);
            } else {
                // Kalau item ini bagian dari PO yang sedang dipilih, isi Qty PO
                // dari situ supaya indikator sesuai/selisih bekerja walau item
                // ditambah lewat scan (bukan dipilih manual dari daftar PO).
                const poItem = this.currentPoItems.find(pi => Number(pi.item_id) === Number(item.id));
                this.rows.push({
                    key: `${Date.now()}-${Math.random()}`,
                    item_id: String(item.id),
                    unit_id: String(item.purchase_unit_id || item.inventory_unit_id || item.base_unit_id || ''),
                    qty_ordered: poItem ? String(poItem.qty_ordered) : '0',
                    qty_received: '1',
                    unit_price: '0',
                    expired_date: '',
                    batch_code: '',
                    notes: '',
                    variance_reason: '',
                    track_expiry: item.track_expiry === true,
                });
            }
        },

        capturePhoto(inputName) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.capture = 'environment';
            input.onchange = () => {
                if (!input.files || !input.files[0]) return;
                const realInput = document.querySelector(`[name="${inputName}"]`);
                if (!realInput) return;
                const dt = new DataTransfer();
                dt.items.add(input.files[0]);
                realInput.files = dt.files;
            };
            input.click();
        },

        selectedItem(row) {
            return this.items.find((item) => Number(item.id) === Number(row.item_id));
        },
        applyItem(row) {
            const item = this.selectedItem(row);
            if (!item) return;
            row.unit_id = item.purchase_unit_id || item.inventory_unit_id || item.base_unit_id || '';
            row.track_expiry = item.track_expiry === true;
        },
        normalize(value) {
            if (value === null || value === undefined || value === '') return 0;
            const text = String(value).replace(',', '.');
            const parsed = Number.parseFloat(text);
            return Number.isFinite(parsed) ? parsed : 0;
        },
        rowTotal(row) {
            return this.normalize(row.qty_received) * this.normalize(row.unit_price);
        },
        grandTotal() {
            return this.rows.reduce((sum, row) => sum + this.rowTotal(row), 0);
        },
        // Selisih qty terima vs qty PO. 0 kalau tidak ada Qty PO (bukan konteks PO,
        // mis. Supplier Luar / item ditambah manual tanpa terhubung PO).
        rowVariance(row) {
            const ordered = this.normalize(row.qty_ordered);
            if (ordered <= 0) return 0;
            return this.normalize(row.qty_received) - ordered;
        },
        formatQty(value) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(value || 0);
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);
        },
        // Jaga-jaga di sisi klien — validasi sebenarnya tetap di server
        // (GoodsReceiptController::validated()). Ini cuma supaya user langsung
        // tahu baris mana yang belum diisi alasannya, tanpa nunggu round-trip.
        handleSubmit(event) {
            const unresolved = this.rows
                .map((row, index) => ({ row, index }))
                .filter(({ row }) => this.rowVariance(row) !== 0 && !row.variance_reason);

            if (unresolved.length > 0) {
                event.preventDefault();
                const lines = unresolved.map(({ index }) => `Baris ${index + 1}`).join(', ');
                alert(`Isi dulu "Alasan Selisih" untuk item yang qty-nya beda dari PO: ${lines}.`);
            }
        },
        init() {
            this.rows.forEach((row) => {
                const item = this.selectedItem(row);
                row.track_expiry = item ? item.track_expiry === true : false;
            });
        },
    };
}
</script>
@endpush
