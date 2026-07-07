@php $isEdit = isset($openStock); @endphp

<div class="px-4 pt-4 pb-32 lg:px-6 space-y-4">

    {{-- Outlet, target, tanggal --}}
    <x-sf.card title="Detail Transaksi">
        <div class="space-y-4">
            <x-sf.form-group label="Outlet" for="outlet_id" :required="true">
                <select id="outlet_id" name="outlet_id" required class="sf-input">
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}"
                            @selected((string) old('outlet_id', $openStock->outlet_id ?? '') === (string) $outlet->id)>
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Target Stok" for="stock_target" :required="true">
                <select id="stock_target" name="stock_target" required class="sf-input">
                    @foreach($targetOptions as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('stock_target', $openStock->stock_target ?? 'OUTLET_DAILY') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Tanggal Bisnis" for="business_date" :required="true">
                <input
                    type="date"
                    id="business_date"
                    name="business_date"
                    value="{{ old('business_date', isset($openStock) ? $openStock->business_date->toDateString() : now()->toDateString()) }}"
                    required
                    class="sf-input"
                >
            </x-sf.form-group>
        </div>
    </x-sf.card>

    {{-- Item & qty --}}
    <x-sf.card title="Item & Qty">
        <div class="space-y-4">
            <x-sf.form-group label="Item" for="item_id" :required="true" :error="$errors->first('item_id')">
                <select id="item_id" name="item_id" required class="sf-input">
                    @foreach($items as $item)
                        <option value="{{ $item->id }}"
                            @selected((string) old('item_id', $openStock->item_id ?? '') === (string) $item->id)>
                            {{ $item->name }} — {{ $item->canonical_sku }}
                            (Inv: {{ $item->inventoryUnit?->code }} / Base: {{ $item->baseUnit?->code ?? $item->inventoryUnit?->code }})
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <div class="grid grid-cols-2 gap-3">
                <x-sf.form-group label="Qty Utuh" for="qty_whole" :required="true" :error="$errors->first('qty_whole')">
                    <input
                        type="text"
                        inputmode="decimal"
                        id="qty_whole"
                        name="qty_whole"
                        value="{{ old('qty_whole', $openStock->qty_whole ?? '0') }}"
                        required
                        class="sf-input"
                    >
                </x-sf.form-group>

                <x-sf.form-group label="Qty Ecer" for="qty_loose" :required="true" :error="$errors->first('qty_loose')">
                    <input
                        type="text"
                        inputmode="decimal"
                        id="qty_loose"
                        name="qty_loose"
                        value="{{ old('qty_loose', $openStock->qty_loose ?? '0') }}"
                        required
                        class="sf-input"
                    >
                </x-sf.form-group>
            </div>

            <x-sf.form-group label="HPP / Cost per Unit" for="cost_per_unit"
                hint="Opsional. Untuk perhitungan nilai stok.">
                <input
                    type="text"
                    inputmode="decimal"
                    id="cost_per_unit"
                    name="cost_per_unit"
                    value="{{ old('cost_per_unit', $openStock->cost_per_unit ?? '') }}"
                    class="sf-input"
                    placeholder="0"
                >
            </x-sf.form-group>

            <x-sf.form-group label="Catatan" for="notes">
                <textarea
                    id="notes"
                    name="notes"
                    rows="3"
                    class="sf-input resize-none"
                    placeholder="Opsional..."
                >{{ old('notes', $openStock->notes ?? '') }}</textarea>
            </x-sf.form-group>
        </div>
    </x-sf.card>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold mb-1">Terdapat kesalahan:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<x-mobile.sticky-submit>
    <button type="submit" class="sf-btn-primary w-full text-base">
        {{ $isEdit ? '💾 Simpan Perubahan Draft' : '💾 Buat Draft' }}
    </button>
</x-mobile.sticky-submit>
