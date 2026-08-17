@extends('layouts.app')

@section('title', $recipe ? 'Edit Resep' : 'Resep Versi Baru')

@section('content')
<x-sf.page-header
    title="{{ $recipe ? 'Edit Resep — Versi '.$recipe->version_number : 'Resep Versi Baru' }}"
    subtitle="{{ $menu->name }}"
    back="{{ $recipe ? route('production.recipes.show', $recipe) : route('production.menus.show', $menu) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full"
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
     })">
    <form method="POST" action="{{ $recipe ? route('production.recipes.update', $recipe) : route('production.recipes.store', $menu) }}" class="space-y-4">
        @csrf
        @if($recipe)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <x-sf.card title="Info R&D">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-sf.form-group label="Tanggal Tes/Food Panel" for="test_date">
                    <input type="date" id="test_date" name="test_date" value="{{ old('test_date', optional($recipe?->test_date)->format('Y-m-d')) }}" class="sf-input text-base">
                </x-sf.form-group>

                <x-sf.form-group label="Volume Produksi (unit dihasilkan per batch)" for="volume_production" :required="true">
                    <input type="text" inputmode="decimal" id="volume_production" name="volume_production" value="{{ old('volume_production', $recipe?->volume_production ?? 1) }}" class="sf-input text-base" required>
                </x-sf.form-group>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <x-sf.form-group label="Disaksikan Oleh (user sistem)" for="witnessed_by_user_ids">
                    <select id="witnessed_by_user_ids" name="witnessed_by_user_ids[]" multiple class="sf-input text-base h-28">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array($user->id, old('witnessed_by_user_ids', $recipe?->witnessed_by_user_ids ?? [])))>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Ctrl/Cmd+klik untuk pilih lebih dari satu</p>
                </x-sf.form-group>

                <x-sf.form-group label="Disaksikan Oleh (nama bebas, pisahkan koma)" for="witnessed_by_names">
                    <input id="witnessed_by_names" name="witnessed_by_names" value="{{ old('witnessed_by_names', $recipe?->witnessed_by_names) }}" class="sf-input text-base" placeholder="mis. tamu/customer yang ikut menyaksikan">
                </x-sf.form-group>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <x-sf.form-group label="Food Panel Oleh (user sistem)" for="food_panel_user_ids">
                    <select id="food_panel_user_ids" name="food_panel_user_ids[]" multiple class="sf-input text-base h-28">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array($user->id, old('food_panel_user_ids', $recipe?->food_panel_user_ids ?? [])))>{{ $user->name }}</option>
                        @endforeach
                    </select>
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
                    </div>
                </template>
            </div>

            <button type="button" @click="addIngredient()" class="sf-btn-secondary w-full mt-3 min-h-11">+ Tambah Bahan</button>
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
        </x-sf.card>

        <button type="submit" class="sf-btn-primary w-full min-h-12">{{ $recipe ? 'Simpan Perubahan' : 'Simpan Draft' }}</button>
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

        addIngredient() {
            this.ingredients.push({ item_id: '', buy_qty: '', buy_unit_id: '', buy_price: '', recipe_qty: '', recipe_unit_id: '' });
        },
        addOtherCost() {
            this.otherCosts.push({ cost_type: 'PRODUCTION', label: '', amount: '' });
        },
    };
}
</script>
@endpush
