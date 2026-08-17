@extends('layouts.app')

@section('title', 'Kalkulator HPP')

@section('content')
<x-sf.page-header
    title="Kalkulator HPP"
    subtitle="Coba-coba hitung HPP sebelum resmi jadi resep — tidak perlu bikin Menu & Resep dulu"
    back="{{ route('production.menus.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div x-data="hppCalculator()">
        <form method="POST" action="{{ route('production.hpp-calculator.store') }}">
            @csrf
            <input type="hidden" name="ingredients_total" :value="ingredientsTotal().toFixed(4)">
            <input type="hidden" name="other_costs_total" :value="otherCostsTotal().toFixed(4)">
            <input type="hidden" name="total_cost" :value="totalCost().toFixed(4)">
            <input type="hidden" name="hpp_per_unit" :value="hppPerUnit().toFixed(4)">
            <template x-for="(row, index) in ingredients" :key="'ing-hidden-'+index">
                <input type="hidden" :name="`ingredients[${index}][cost]`" :value="rowCost(row).toFixed(4)">
            </template>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                <div class="xl:col-span-8 space-y-4">
                    <x-sf.card title="Produk">
                        <x-sf.form-group label="Nama Produk / Menu" for="product_name" :required="true">
                            <input id="product_name" name="product_name" x-model="productName" class="sf-input text-base" placeholder="Contoh: Kopi Susu Gula Aren (versi coba-coba)" required>
                        </x-sf.form-group>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <x-sf.form-group label="Outlet (opsional)" for="outlet_id">
                                <select id="outlet_id" name="outlet_id" class="sf-input text-base">
                                    <option value="">- Tidak spesifik -</option>
                                    @foreach($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </x-sf.form-group>
                            <x-sf.form-group label="Volume Produksi (unit dihasilkan per batch)" for="volume_production" :required="true">
                                <input type="text" inputmode="decimal" id="volume_production" name="volume_production" x-model="volumeProduction" class="sf-input text-base" required>
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

                                    <input type="text" :name="`ingredients[${index}][name]`" x-model="row.name" class="sf-input text-base min-h-11" placeholder="Nama bahan (mis. Gula Aren Cair)" required>

                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="sf-label">Qty Beli</label>
                                            <div class="flex">
                                                <input type="text" inputmode="decimal" :name="`ingredients[${index}][buy_qty]`" x-model="row.buy_qty" class="sf-input text-base min-h-11 rounded-r-none" required>
                                                <select :name="`ingredients[${index}][buy_unit]`" x-model="row.buy_unit" class="sf-input text-base min-h-11 w-20 rounded-l-none border-l-0">
                                                    <option value="kg">kg</option>
                                                    <option value="gr">gr</option>
                                                    <option value="lt">lt</option>
                                                    <option value="ml">ml</option>
                                                    <option value="pcs">pcs</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="sf-label">Harga Beli</label>
                                            <input type="text" inputmode="decimal" :name="`ingredients[${index}][buy_price]`" x-model="row.buy_price" class="sf-input text-base min-h-11" placeholder="Rp" required>
                                        </div>
                                        <div class="col-span-2 md:col-span-1">
                                            <label class="sf-label">Pemakaian Resep</label>
                                            <div class="flex">
                                                <input type="text" inputmode="decimal" :name="`ingredients[${index}][recipe_qty]`" x-model="row.recipe_qty" class="sf-input text-base min-h-11 rounded-r-none" required>
                                                <select :name="`ingredients[${index}][recipe_unit]`" x-model="row.recipe_unit" class="sf-input text-base min-h-11 w-20 rounded-l-none border-l-0">
                                                    <option value="gr">gr</option>
                                                    <option value="kg">kg</option>
                                                    <option value="ml">ml</option>
                                                    <option value="lt">lt</option>
                                                    <option value="pcs">pcs</option>
                                                </select>
                                            </div>
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

                <div class="xl:col-span-4">
                    <div class="bg-primary-700 text-white p-6 rounded-2xl shadow-xl xl:sticky xl:top-6 space-y-4">
                        <h3 class="text-base font-bold flex items-center gap-2">
                            <i class="ti ti-calculator" aria-hidden="true"></i>
                            Hasil Perhitungan
                        </h3>

                        <div class="bg-white/10 p-4 rounded-xl border border-white/10">
                            <p class="text-[10px] text-primary-100 font-bold uppercase tracking-wider mb-1">Total Biaya (per batch)</p>
                            <p class="text-2xl font-bold" x-text="formatIDR(totalCost())"></p>
                        </div>

                        <div class="bg-white/20 p-4 rounded-xl border border-white/30 ring-2 ring-white/10">
                            <p class="text-[10px] text-primary-100 font-bold uppercase tracking-wider mb-1">HPP per Unit</p>
                            <p class="text-2xl font-bold" x-text="formatIDR(hppPerUnit())"></p>
                        </div>

                        <button type="submit" class="w-full bg-white text-primary-700 py-3.5 rounded-xl font-extrabold shadow-lg hover:bg-gray-50 transition-colors">
                            Simpan ke Riwayat
                        </button>
                        <p class="text-[11px] text-primary-100 text-center">Ini cuma catatan coba-coba, bukan resep resmi. Kalau sudah pas, buat resmi lewat Menu &amp; Resep.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <x-sf.card title="Riwayat Perhitungan">
        @if($history->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada perhitungan yang disimpan.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($history as $calc)
                    <div class="flex items-center justify-between gap-3 px-3 py-3">
                        <a href="{{ route('production.hpp-calculator.show', $calc) }}" class="min-w-0 flex-1 hover:text-primary-700">
                            <p class="font-semibold text-gray-900 truncate">{{ $calc->product_name }}</p>
                            <p class="text-xs text-gray-500">
                                oleh {{ $calc->createdBy?->name ?? '-' }} &middot; {{ $calc->created_at->format('d M Y H:i') }}
                                &middot; HPP/unit: Rp {{ number_format((float) $calc->hpp_per_unit, 0, ',', '.') }}
                            </p>
                        </a>
                        <form method="POST" action="{{ route('production.hpp-calculator.destroy', $calc) }}" onsubmit="return confirm('Hapus riwayat ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs font-semibold">Hapus</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>
    <div>{{ $history->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function hppCalculator() {
    return {
        productName: '',
        volumeProduction: '1',
        ingredients: [{ name: '', buy_qty: '', buy_unit: 'kg', buy_price: '', recipe_qty: '', recipe_unit: 'gr' }],
        otherCosts: [],

        addIngredient() {
            this.ingredients.push({ name: '', buy_qty: '', buy_unit: 'kg', buy_price: '', recipe_qty: '', recipe_unit: 'gr' });
        },
        addOtherCost() {
            this.otherCosts.push({ cost_type: 'PRODUCTION', label: '', amount: '' });
        },

        // Kalkulator coba-coba: konversi satuan sederhana ala Weapix (bukan
        // konversi item master SIFOBI) — kg/lt dianggap 1000x gr/ml, satuan
        // lain dianggap sama skalanya. Cukup untuk simulasi cepat.
        factor(unit) {
            return (unit === 'kg' || unit === 'lt') ? 1000 : 1;
        },
        rowCost(row) {
            const buyQty = parseFloat(row.buy_qty) || 0;
            if (buyQty <= 0) return 0;
            const buyBase = buyQty * this.factor(row.buy_unit);
            const recipeBase = (parseFloat(row.recipe_qty) || 0) * this.factor(row.recipe_unit);
            if (buyBase <= 0) return 0;
            return (recipeBase / buyBase) * (parseFloat(row.buy_price) || 0);
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
