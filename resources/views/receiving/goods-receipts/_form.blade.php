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
        ]];
    }

    $unitsForAlpine = $units->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => $unit->code.' - '.$unit->name,
        'abbreviation' => $unit->abbreviation,
    ])->values()->all();
@endphp

<form method="POST"
      action="{{ $formAction }}"
      enctype="multipart/form-data"
      x-data="goodsReceiptForm({
          items: @js($itemsForAlpine),
          units: @js($unitsForAlpine),
          rows: @js($rows)
      })"
      class="space-y-4">
    @csrf
    @if(($formMethod ?? 'POST') !== 'POST')
        @method($formMethod)
    @endif

    <input type="hidden" name="source" value="{{ $activeSource }}">

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
                <label class="sf-label">Foto Dokumen</label>
                <input type="file" name="photo_document" accept="image/png,image/jpeg,image/webp" class="sf-input text-base min-h-11">
                @if($receipt->photo_document)
                    <p class="text-xs text-gray-500 mt-1">Dokumen lama tersimpan. Upload baru untuk mengganti.</p>
                @endif
            </div>
        </div>
    </x-sf.card>

    <x-sf.card title="Detail Sumber">
        @if($activeSource === 'OCIA_PO')
            <label class="sf-label">Nomor PO OCIA</label>
            <input type="text" name="external_po_number" value="{{ old('external_po_number', $receipt->external_po_number) }}" class="sf-input text-base min-h-11" maxlength="120">
            <p class="text-sm text-gray-500 mt-2">Integrasi OCIA belum aktif; item tetap diisi manual pada versi ini.</p>
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
            <p class="text-sm text-gray-500 mt-2">OCR dokumen belum diaktifkan; data invoice perlu diverifikasi manual.</p>
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
                            <input type="text" inputmode="decimal" :name="`items[${index}][qty_ordered]`" x-model="row.qty_ordered" class="sf-input text-base min-h-11">
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

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3" x-show="row.track_expiry">
                        <div>
                            <label class="sf-label">Expired Date</label>
                            <input type="date" :name="`items[${index}][expired_date]`" x-model="row.expired_date" class="sf-input text-base min-h-11">
                        </div>
                        <div>
                            <label class="sf-label">Batch Code</label>
                            <input type="text" :name="`items[${index}][batch_code]`" x-model="row.batch_code" class="sf-input text-base min-h-11">
                        </div>
                        <div>
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

        <button type="button" @click="addRow()" class="sf-btn-secondary min-h-11 px-4 mt-4 w-full md:w-auto">+ Tambah Baris</button>
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
<script>
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
        track_expiry: false,
    });

    const rows = (config.rows || []).map((row) => ({
        key: `${Date.now()}-${Math.random()}`,
        track_expiry: false,
        ...row,
    }));

    if (rows.length === 0) rows.push(blankRow());

    return {
        items: config.items || [],
        units: config.units || [],
        rows,
        addRow() {
            this.rows.push(blankRow());
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
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
        formatCurrency(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);
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
