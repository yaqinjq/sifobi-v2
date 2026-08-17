@extends('layouts.app')

@section('title', $recipe ? 'Edit Resep' : 'Resep Versi Baru')

@section('content')
<x-sf.page-header
    title="{{ $recipe ? 'Edit Resep — Versi '.$recipe->version_number : 'Resep Versi Baru' }}"
    subtitle="{{ $menu->name }}"
    back="{{ $recipe ? route('production.recipes.show', $recipe) : route('production.menus.show', $menu) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full"
     x-data="recipeForm({
         items: @js($items),
         ingredients: @js(old('ingredients', $recipe?->ingredients?->map(fn ($i) => [
             'item_id' => (string) $i->item_id,
             'buy_qty' => (string) $i->buy_qty,
             'buy_unit_id' => (string) $i->buy_unit_id,
             'buy_price' => (string) $i->buy_price,
             'recipe_qty' => (string) $i->recipe_qty,
             'recipe_unit_id' => (string) $i->recipe_unit_id,
         ])->values()->all() ?? [])),
         otherCosts: @js(old('other_costs', $recipe?->otherCosts?->map(fn ($c) => [
             'cost_type' => $c->cost_type,
             'label' => $c->label,
             'amount' => (string) $c->amount,
         ])->values()->all() ?? [])),
         volumeProduction: @js(old('volume_production', (string) ($recipe?->volume_production ?? 1))),
     })">
    <form method="POST" action="{{ $recipe ? route('production.recipes.update', $recipe) : route('production.recipes.store', $menu) }}">
        @csrf
        @if($recipe)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1 mb-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            {{-- Kiri: form input --}}
            <div class="xl:col-span-8 space-y-4">
                <x-sf.card title="Info R&D">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-sf.form-group label="Tanggal Tes/Food Panel" for="test_date">
                            <input type="date" id="test_date" name="test_date" value="{{ old('test_date', optional($recipe?->test_date)->format('Y-m-d')) }}" class="sf-input text-base">
                        </x-sf.form-group>

                        <x-sf.form-group label="Volume Produksi (unit dihasilkan per batch)" for="volume_production" :required="true">
                            <input type="text" inputmode="decimal" id="volume_production" name="volume_production" x-model="volumeProduction" class="sf-input text-base" required>
                        </x-sf.form-group>
                    </div>

                    @php
                        $userOptions = $users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->values();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <x-sf.form-group label="Disaksikan Oleh (user sistem)" for="witnessed_by_user_ids">
                            <x-sf.multi-select
                                name="witnessed_by_user_ids[]"
                                :options="$userOptions"
                                :selected="old('witnessed_by_user_ids', $recipe?->witnessed_by_user_ids ?? [])"
                                placeholder="Cari nama..."
                            />
                        </x-sf.form-group>

                        <x-sf.form-group label="Disaksikan Oleh (nama bebas, pisahkan koma)" for="witnessed_by_names">
                            <input id="witnessed_by_names" name="witnessed_by_names" value="{{ old('witnessed_by_names', $recipe?->witnessed_by_names) }}" class="sf-input text-base" placeholder="mis. tamu/customer yang ikut menyaksikan">
                        </x-sf.form-group>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <x-sf.form-group label="Food Panel Oleh (user sistem)" for="food_panel_user_ids">
                            <x-sf.multi-select
                                name="food_panel_user_ids[]"
                                :options="$userOptions"
                                :selected="old('food_panel_user_ids', $recipe?->food_panel_user_ids ?? [])"
                                placeholder="Cari nama..."
                            />
                        </x-sf.form-group>

                        <x-sf.form-group label="Food Panel Oleh (nama bebas, pisahkan koma)" for="food_panel_names">
                            <input id="food_panel_names" name="food_panel_names" value="{{ old('food_panel_names', $recipe?->food_panel_names) }}" class="sf-input text-base">
                        </x-sf.form-group>
                    </div>

                    <div class="mt-4">
                        <x-sf.form-group label="Catatan" for="notes">
                            <textarea id="notes" name="notes" rows="2" class="sf-input text-base">{{ old('notes', $recipe?->notes) }}</textarea>
                        </x-sf.form-group>
                    </div>
                </x-sf.card>

                <x-sf.card title="Bahan Baku">
                    <div class="space-y-3">
                        <template x-for="(row, index) in ingredients" :key="index">
                            <div class="rounded-xl border border-gray-200 bg-white p-3 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-gray-900">Bahan <span x-text="index + 1"></span></p>
                                    <button type="button" @click="ingredients.splice(index, 1)" class="text-red-400 hover:text-red-600 text-xs font-semibold" x-show="ingredients.length > 1">Hapus</button>
                                </div>

                                <div>
                                    <label class="sf-label">Item *</label>
                                    <select :name="`ingredients[${index}][item_id]`" x-model="row.item_id" class="sf-input text-base min-h-11" required>
                                        <option value="">Pilih item</option>
                                        <template x-for="item in items" :key="item.id">
                                            <option :value="item.id" x-text="`${item.name}${item.sku ? ' - ' + item.sku : ''}`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="sf-label">Qty Pembelian *</label>
                                        <input type="text" inputmode="decimal" :name="`ingredients[${index}][buy_qty]`" x-model="row.buy_qty" class="sf-input text-base min-h-11" required>
                                    </div>
                                    <div>
                                        <label class="sf-label">Satuan Beli *</label>
                                        <select :name="`ingredients[${index}][buy_unit_id]`" x-model="row.buy_unit_id" class="sf-input text-base min-h-11" required>
                                            <option value="">Satuan</option>
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-span-2 md:col-span-1">
                                        <label class="sf-label">Harga Beli (manual) *</label>
                                        <input type="text" inputmode="decimal" :name="`ingredients[${index}][buy_price]`" x-model="row.buy_price" class="sf-input text-base min-h-11" placeholder="Rp" required>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="sf-label">Qty Pemakaian Resep *</label>
                                        <input type="text" inputmode="decimal" :name="`ingredients[${index}][recipe_qty]`" x-model="row.recipe_qty" class="sf-input text-base min-h-11" required>
                                    </div>
                                    <div>
                                        <label class="sf-label">Satuan Resep *</label>
                                        <select :name="`ingredients[${index}][recipe_unit_id]`" x-model="row.recipe_unit_id" class="sf-input text-base min-h-11" required>
                                            <option value="">Satuan</option>
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-gray-100 flex items-center justify-between">
                                    <span class="text-xs text-gray-400 italic">Biaya pemakaian bahan ini:</span>
                                    <span class="text-sm font-bold text-green-600" x-text="formatIDR(rowCost(row))"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addIngredient()" class="sf-btn-secondary w-full mt-3 min-h-11">+ Tambah Bahan</button>

                    <div class="mt-4 pt-3 border-t-2 border-gray-100 flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-600">Total Bahan Baku:</span>
                        <span class="text-lg font-bold text-primary-700" x-text="formatIDR(ingredientsTotal())"></span>
                    </div>
                </x-sf.card>

                <x-sf.card title="Biaya Produksi & Overhead">
                    <div class="space-y-3">
                        <template x-for="(row, index) in otherCosts" :key="index">
                            <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
                                <select :name="`other_costs[${index}][cost_type]`" x-model="row.cost_type" class="sf-input text-base min-h-11 sm:w-40" required>
                                    <option value="PRODUCTION">Produksi</option>
                                    <option value="OVERHEAD">Overhead</option>
                                </select>
                                <input type="text" :name="`other_costs[${index}][label]`" x-model="row.label" class="sf-input text-base min-h-11 flex-1" placeholder="Nama biaya (mis. Listrik)" required>
                                <input type="text" inputmode="decimal" :name="`other_costs[${index}][amount]`" x-model="row.amount" class="sf-input text-base min-h-11 sm:w-32" placeholder="Rp" required>
                                <button type="button" @click="otherCosts.splice(index, 1)" class="text-red-400 hover:text-red-600 text-xs font-semibold shrink-0">Hapus</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addOtherCost()" class="sf-btn-secondary w-full mt-3 min-h-11">+ Tambah Biaya</button>

                    <div class="mt-4 pt-3 border-t-2 border-gray-100 flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-600">Total Biaya Produksi & Overhead:</span>
                        <span class="text-lg font-bold text-primary-700" x-text="formatIDR(otherCostsTotal())"></span>
                    </div>
                </x-sf.card>
            </div>

            {{-- Kanan: kalkulator HPP real-time --}}
            <div class="xl:col-span-4">
                <div class="bg-primary-700 text-white p-6 rounded-2xl shadow-xl xl:sticky xl:top-6 space-y-4">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <i class="ti ti-calculator" aria-hidden="true"></i>
                        Hasil Perhitungan HPP
                    </h3>

                    <div class="bg-white/10 p-4 rounded-xl border border-white/10">
                        <p class="text-[10px] text-primary-100 font-bold uppercase tracking-wider mb-1">Total Biaya (per batch)</p>
                        <p class="text-2xl font-bold" x-text="formatIDR(totalCost())"></p>
                    </div>

                    <div class="bg-white/20 p-4 rounded-xl border border-white/30 ring-2 ring-white/10">
                        <p class="text-[10px] text-primary-100 font-bold uppercase tracking-wider mb-1">HPP per Unit</p>
                        <p class="text-2xl font-bold" x-text="formatIDR(hppPerUnit())"></p>
                        <p class="text-xs text-primary-100 mt-1">
                            <span x-text="formatIDR(totalCost())"></span> &divide; <span x-text="volumeProduction || 0"></span> unit
                        </p>
                    </div>

                    <div class="text-xs text-primary-100 space-y-1 px-1">
                        <div class="flex justify-between"><span>Bahan Baku</span><span x-text="formatIDR(ingredientsTotal())"></span></div>
                        <div class="flex justify-between"><span>Produksi & Overhead</span><span x-text="formatIDR(otherCostsTotal())"></span></div>
                    </div>

                    <button type="submit" class="w-full bg-white text-primary-700 py-3.5 rounded-xl font-extrabold shadow-lg hover:bg-gray-50 transition-colors">
                        {{ $recipe ? 'Simpan Perubahan' : 'Simpan Draft' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function recipeForm(config) {
    return {
        items: config.items || [],
        ingredients: (config.ingredients && config.ingredients.length) ? config.ingredients : [{ item_id: '', buy_qty: '', buy_unit_id: '', buy_price: '', recipe_qty: '', recipe_unit_id: '' }],
        otherCosts: config.otherCosts || [],
        volumeProduction: config.volumeProduction || '1',

        addIngredient() {
            this.ingredients.push({ item_id: '', buy_qty: '', buy_unit_id: '', buy_price: '', recipe_qty: '', recipe_unit_id: '' });
        },
        addOtherCost() {
            this.otherCosts.push({ cost_type: 'PRODUCTION', label: '', amount: '' });
        },

        findItem(itemId) {
            return this.items.find((i) => String(i.id) === String(itemId));
        },

        // Mirror RecipeIngredient::toBaseQty() di server (app/Modules/Production/Models/RecipeIngredient.php)
        // supaya angka yang tampil di sini SAMA PERSIS dengan HPP yang dihitung ulang saat disimpan.
        toBaseQty(item, unitId, qty) {
            qty = parseFloat(qty) || 0;
            if (!item || !unitId) return 0;
            unitId = String(unitId);

            let factor = 1;
            if (String(item.base_unit_id) === unitId) {
                factor = 1;
            } else if (String(item.inventory_unit_id) === unitId && item.inventory_ratio) {
                factor = item.inventory_ratio;
            } else if (String(item.purchase_unit_id) === unitId && item.purchase_ratio) {
                factor = item.purchase_ratio;
            } else {
                const conversion = (item.conversions || []).find((c) => String(c.from_unit_id) === unitId && String(c.to_unit_id) === String(item.base_unit_id));
                factor = conversion ? conversion.factor : 1;
            }

            return qty * factor;
        },

        rowCost(row) {
            const item = this.findItem(row.item_id);
            const buyQty = parseFloat(row.buy_qty) || 0;
            if (!item || buyQty <= 0) return 0;

            const recipeQtyBase = this.toBaseQty(item, row.recipe_unit_id, row.recipe_qty);
            const buyQtyBase = this.toBaseQty(item, row.buy_unit_id, row.buy_qty);
            if (buyQtyBase <= 0) return 0;

            return (recipeQtyBase / buyQtyBase) * (parseFloat(row.buy_price) || 0);
        },

        ingredientsTotal() {
            return this.ingredients.reduce((sum, row) => sum + this.rowCost(row), 0);
        },
        otherCostsTotal() {
            return this.otherCosts.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
        },
        totalCost() {
            return this.ingredientsTotal() + this.otherCostsTotal();
        },
        hppPerUnit() {
            const volume = parseFloat(this.volumeProduction) || 0;
            if (volume <= 0) return 0;
            return this.totalCost() / volume;
        },

        formatIDR(value) {
            return 'Rp ' + Math.round(value || 0).toLocaleString('id-ID');
        },
    };
}
</script>
@endpush
