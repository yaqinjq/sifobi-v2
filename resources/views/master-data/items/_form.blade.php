@php
    $isEdit = $item->exists;
    $selectedBaseUnit = old('base_unit_id', $item->base_unit_id);
    $selectedInventoryUnit = old('inventory_unit_id', $item->inventory_unit_id && $item->inventory_unit_id !== $item->base_unit_id ? $item->inventory_unit_id : '');
    $selectedPurchaseUnit = old('purchase_unit_id', $item->purchase_unit_id);
    $selectedJenisId = old('item_jenis_id', $item->item_jenis_id);
    $selectedCategoryId = old('item_category_id', $item->item_category_id);
    $selectedDepartmentIds = collect(old('department_ids', $isEdit ? $item->departments->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->values()->all();
    $selectedOutletIds = collect(old('outlet_ids', $isEdit ? $item->outlets->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->values()->all();
    $conversionRows = collect(old('extra_conversions', $isEdit ? $item->conversions->map(fn ($conversion) => [
        'from_unit_id' => $conversion->from_unit_id,
        'to_unit_id' => $conversion->to_unit_id,
        'factor' => rtrim(rtrim((string) ($conversion->factor ?? $conversion->multiply_rate), '0'), '.'),
    ])->all() : []))->values()->all();
    $aliasRows = $isEdit ? $item->brandAliases->map(fn ($alias) => [
        'id' => $alias->id,
        'brand_id' => $alias->brand_id,
        'brand_name' => $alias->brand?->name,
        'brand_sku' => $alias->brand_sku,
        'brand_item_name' => $alias->brand_item_name,
        'is_primary' => (bool) $alias->is_primary,
        'destroy_url' => route('master-data.items.aliases.destroy', [$item, $alias]),
    ])->values()->all() : [];
    $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
@endphp

<form method="POST"
      action="{{ $action }}"
      enctype="multipart/form-data"
      class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5"
      x-data="itemForm({
        sku: @js(old('canonical_sku', $item->canonical_sku)),
        name: @js(old('name', $item->name)),
        itemType: @js(old('item_type', $item->item_type ?? 'BAHAN_BAKU')),
        isActive: @js((bool) old('is_active', $item->is_active ?? true)),
        trackExpiry: @js((bool) old('track_expiry', $item->track_expiry ?? false)),
        baseUnit: @js((string) $selectedBaseUnit),
        inventoryUnit: @js((string) $selectedInventoryUnit),
        purchaseUnit: @js((string) $selectedPurchaseUnit),
        inventoryRatio: @js(old('inventory_ratio', $item->inventory_ratio)),
        purchaseRatio: @js(old('purchase_ratio', $item->purchase_ratio)),
        selectedDepartmentIds: @js($selectedDepartmentIds),
        selectedOutletIds: @js($selectedOutletIds),
        allOutletIds: @js($outlets->pluck('id')->map(fn ($id) => (int) $id)->values()),
        photoPreview: @js($photoUrl),
        conversionRows: @js($conversionRows),
        units: @js($units->map(fn ($unit) => ['id' => (string) $unit->id, 'label' => $unit->abbreviation ?: $unit->code])->values()),
      })">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="is_active" :value="isActive ? 1 : 0">
    <input type="hidden" name="track_expiry" :value="trackExpiry ? 1 : 0">
    <input type="hidden" name="sync_extra_conversions" value="1">

    <template x-for="departmentId in selectedDepartmentIds" :key="`dept-${departmentId}`">
        <input type="hidden" name="department_ids[]" :value="departmentId">
    </template>

    <template x-for="outletId in selectedOutletIds" :key="`outlet-${outletId}`">
        <input type="hidden" name="outlet_ids[]" :value="outletId">
    </template>

    <x-sf.card title="Identitas Bahan">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="space-y-3">
                <button type="button"
                        class="w-full aspect-square rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center text-center active:scale-[.99] transition-transform"
                        @click="$refs.photoInput.click()"
                        @dragover.prevent
                        @drop.prevent="setDroppedPhoto($event)">
                    <template x-if="photoPreview">
                        <img :src="photoPreview" alt="Preview foto item" class="h-full w-full object-cover">
                    </template>
                    <template x-if="!photoPreview">
                        <div class="px-4">
                            <div class="text-sm font-bold text-gray-500 mb-2">FOTO</div>
                            <p class="text-sm font-semibold text-gray-800">Klik/Drop Foto</p>
                            <p class="text-xs text-gray-500 mt-1">Max 3MB, JPG/PNG</p>
                        </div>
                    </template>
                </button>
                <input x-ref="photoInput" type="file" name="photo" accept="image/jpeg,image/png" class="hidden" @change="setPhoto($event)">
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                    <p class="text-xs text-gray-500">SKU</p>
                    <p class="font-semibold text-gray-900 break-all" x-text="sku || 'Belum dibuat'"></p>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <x-sf.form-group label="Nama Bahan" for="name" :required="true">
                    <input id="name" name="name" type="text" x-model="name" required maxlength="255" class="sf-input text-base">
                </x-sf.form-group>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <x-sf.form-group label="SKU Internal (Canonical)" for="canonical_sku" :required="true">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input id="canonical_sku" name="canonical_sku" type="text" x-model="sku" required maxlength="50" autocapitalize="characters" class="sf-input text-base uppercase">
                            <button type="button" class="sf-btn-secondary sm:w-auto" @click="generateSku()">Generate</button>
                        </div>
                    </x-sf.form-group>

                    <x-sf.form-group label="Jenis Bahan" for="item_jenis_id" :required="true">
                        <select id="item_jenis_id" name="item_jenis_id" class="sf-input text-base" required>
                            <option value="">Pilih jenis</option>
                            @foreach($jenises as $jenis)
                                <option value="{{ $jenis->id }}" @selected((string) $selectedJenisId === (string) $jenis->id)>
                                    {{ $jenis->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-sf.form-group>

                    <x-sf.form-group label="Kategori Bahan" for="item_category_id">
                        <select id="item_category_id" name="item_category_id" class="sf-input text-base">
                            <option value="">Pilih kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-sf.form-group>
                </div>

                <x-sf.form-group label="Keterangan / Pembeda" for="keterangan_pembeda" hint="Isi jika ada barang sama beda spek, brand, ukuran, atau kualitas.">
                    <input id="keterangan_pembeda" name="keterangan_pembeda" type="text" value="{{ old('keterangan_pembeda', $item->keterangan_pembeda) }}" maxlength="255" class="sf-input text-base">
                </x-sf.form-group>

                <x-sf.form-group label="Deskripsi" for="description">
                    <textarea id="description" name="description" rows="3" class="sf-input text-base resize-none">{{ old('description', $item->description) }}</textarea>
                </x-sf.form-group>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-sf.form-group label="Tipe Item" for="item_type" :required="true">
                        <select id="item_type" name="item_type" x-model="itemType" required class="sf-input text-base">
                            @foreach($itemTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-sf.form-group>

                    <div>
                        <span class="sf-label">Status</span>
                        <button type="button"
                                class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700"
                                @click="isActive = !isActive"
                                :aria-pressed="isActive.toString()">
                            <span class="h-3 w-3 rounded-full bg-primary-700" x-show="isActive"></span>
                            <span class="h-3 w-3 rounded-full bg-gray-300" x-show="!isActive" x-cloak></span>
                            <span x-text="isActive ? 'Aktif' : 'Non-Aktif'"></span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-sf.form-group label="Departemen Utama" for="primary_department_id" :required="true">
                        <select id="primary_department_id" name="primary_department_id" class="sf-input text-base" required>
                            <option value="">Pilih departemen</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('primary_department_id', $item->primary_department_id) == $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-sf.form-group>

                    <x-sf.form-group label="Frekuensi Opname" for="opname_frequency" :required="true">
                        <select id="opname_frequency" name="opname_frequency" required class="sf-input text-base">
                            @foreach($opnameFrequencies as $value => $label)
                                <option value="{{ $value }}" @selected(old('opname_frequency', $item->opname_frequency ?? 'DAILY') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-sf.form-group>
                </div>

                <div>
                    <span class="sf-label">Departemen Pemakai</span>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($departments as $department)
                            <button type="button"
                                    class="min-h-11 rounded-xl border px-3 py-2 text-sm transition-colors"
                                    :class="isDepartmentSelected({{ $department->id }}) ? 'sf-choice-selected' : 'sf-choice-unselected'"
                                    @click="toggleDepartment({{ $department->id }})">
                                {{ $department->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </x-sf.card>

    <x-sf.card title="Kode SKU per Brand (Alias Finance)">
        <p class="text-sm text-gray-500 mb-4">
            Canonical SKU adalah kode global sistem. Finance setiap brand bisa mencatat kode produk mereka sendiri di sini.
        </p>

        @if($isEdit)
            <div x-data="itemAliasEditor({
                    storeUrl: @js(route('master-data.items.aliases.store', $item)),
                    aliases: @js($aliasRows),
                    brands: @js($brands->map(fn ($brand) => ['id' => (int) $brand->id, 'name' => $brand->name])->values()),
                })"
                class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Brand</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode Finance</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama Brand</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Primary</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="alias in aliases" :key="alias.id">
                                <tr class="odd:bg-white even:bg-gray-50/60">
                                    <td class="px-3 py-3 font-semibold text-gray-900" x-text="alias.brand_name"></td>
                                    <td class="px-3 py-3 text-gray-800" x-text="alias.brand_sku"></td>
                                    <td class="px-3 py-3 text-gray-600" x-text="alias.brand_item_name || '-'"></td>
                                    <td class="px-3 py-3">
                                        <span x-show="alias.is_primary" class="badge-active">YA</span>
                                        <span x-show="!alias.is_primary" class="badge-draft">-</span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button" class="sf-btn-danger text-xs px-3 py-1.5 min-h-11" @click="destroy(alias)">Hapus</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="aliases.length === 0">
                                <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada alias brand.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[1fr_1fr_1.4fr_auto_auto] gap-2 items-end">
                    <x-sf.form-group label="Brand" for="alias_brand_id">
                        <select id="alias_brand_id" x-model="form.brand_id" class="sf-input text-base">
                            <option value="">Pilih brand</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </x-sf.form-group>
                    <x-sf.form-group label="Kode Finance" for="alias_brand_sku">
                        <input id="alias_brand_sku" x-model="form.brand_sku" class="sf-input text-base uppercase" maxlength="100">
                    </x-sf.form-group>
                    <x-sf.form-group label="Nama Brand" for="alias_brand_item_name">
                        <input id="alias_brand_item_name" x-model="form.brand_item_name" class="sf-input text-base" maxlength="255">
                    </x-sf.form-group>
                    <label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" x-model="form.is_primary" class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                        Primary
                    </label>
                    <button type="button" class="sf-btn-primary" :disabled="loading" @click="store()">
                        <span x-text="loading ? 'Menyimpan...' : '+ Tambah'"></span>
                    </button>
                </div>
                <p x-show="error" x-cloak class="text-sm text-red-600" x-text="error"></p>
            </div>
        @else
            <div class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                Simpan item terlebih dahulu, lalu tambahkan kode SKU per brand dari halaman detail atau edit item.
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Satuan & Konversi">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-2xl border sf-unit-blue p-4">
                <p class="text-xs font-bold uppercase tracking-wide">Satuan Dasar (Resep)</p>
                <p class="text-sm text-blue-800 mt-1">Unit terkecil di resep.</p>
                <select id="base_unit_id" name="base_unit_id" x-model="baseUnit" required class="sf-input text-base mt-4">
                    <option value="">Pilih satuan</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-blue-700 mt-2">Contoh: gram, ml, pcs.</p>
            </div>

            <div class="rounded-2xl border sf-unit-orange p-4">
                <p class="text-xs font-bold uppercase tracking-wide">Satuan Inventory (Stok)</p>
                <p class="text-sm text-orange-800 mt-1">Unit saat opname di gudang.</p>
                <select id="inventory_unit_id" name="inventory_unit_id" x-model="inventoryUnit" class="sf-input text-base mt-4">
                    <option value="">Sama dengan satuan dasar</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                    @endforeach
                </select>
                <div x-show="inventoryUnit" x-cloak class="mt-3">
                    <label class="text-xs font-semibold text-orange-900">1 <span x-text="unitLabel(inventoryUnit)"></span> =</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input id="inventory_ratio" name="inventory_ratio" type="text" inputmode="decimal" x-model="inventoryRatio" class="sf-input text-base">
                        <span class="text-sm text-orange-900" x-text="unitLabel(baseUnit)"></span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border sf-unit-green p-4">
                <p class="text-xs font-bold uppercase tracking-wide">Satuan Pembelian (PO)</p>
                <p class="text-sm text-green-800 mt-1">Unit beli dari supplier.</p>
                <select id="purchase_unit_id" name="purchase_unit_id" x-model="purchaseUnit" class="sf-input text-base mt-4">
                    <option value="">Tidak diatur</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                    @endforeach
                </select>
                <div x-show="purchaseUnit" x-cloak class="mt-3">
                    <label class="text-xs font-semibold text-green-900">1 <span x-text="unitLabel(purchaseUnit)"></span> =</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input id="purchase_ratio" name="purchase_ratio" type="text" inputmode="decimal" x-model="purchaseRatio" class="sf-input text-base">
                        <span class="text-sm text-green-900" x-text="unitLabel(baseUnit)"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-700" x-text="ratioSummary()"></div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-sf.form-group label="Estimasi Harga Beli" for="last_purchase_price">
                <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
                    <span class="px-4 text-sm font-semibold text-gray-500">Rp</span>
                    <input id="last_purchase_price" name="last_purchase_price" type="text" inputmode="decimal" value="{{ old('last_purchase_price', $item->last_purchase_price) }}" class="block w-full rounded-r-xl border-0 bg-transparent text-base text-gray-900 px-3 py-3 focus:ring-0">
                </div>
            </x-sf.form-group>

            <x-sf.form-group label="Yield" for="yield_pct" hint="80% berarti 1 kg bahan menghasilkan 800 gr siap pakai.">
                <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
                    <input id="yield_pct" name="yield_pct" type="text" inputmode="decimal" value="{{ old('yield_pct', $item->yield_pct ?? '100') }}" class="block w-full rounded-l-xl border-0 bg-transparent text-base text-gray-900 px-4 py-3 focus:ring-0">
                    <span class="px-4 text-sm font-semibold text-gray-500">%</span>
                </div>
            </x-sf.form-group>
        </div>

        <div class="mt-4">
            <span class="sf-label">Batch / Expiry Tracking</span>
            <button type="button"
                    class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700"
                    @click="trackExpiry = !trackExpiry">
                <span class="h-3 w-3 rounded-full bg-primary-700" x-show="trackExpiry"></span>
                <span class="h-3 w-3 rounded-full bg-gray-300" x-show="!trackExpiry" x-cloak></span>
                <span x-text="trackExpiry ? 'Aktif, penerimaan wajib isi expired date' : 'Non-aktif'"></span>
            </button>
        </div>

        <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-4" x-data="{ open: false }">
            <button type="button" class="w-full min-h-11 flex items-center justify-between gap-3 text-left" @click="open = !open">
                <span class="text-sm font-semibold text-gray-900">Konversi Tambahan</span>
                <span class="text-sm font-semibold text-primary-800" x-text="open ? 'Tutup' : '+ Tambah Konversi Lain'"></span>
            </button>
            <div x-show="open" x-collapse class="mt-3 space-y-2">
                <template x-for="(row, index) in conversionRows" :key="index">
                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr_auto] gap-2 items-center">
                        <select :name="`extra_conversions[${index}][from_unit_id]`" x-model="row.from_unit_id" class="sf-input text-base">
                            <option value="">Dari</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </select>
                        <div class="text-center text-sm font-semibold text-gray-400">=</div>
                        <div class="grid grid-cols-[1fr_auto_1fr] gap-2 items-center">
                            <input :name="`extra_conversions[${index}][factor]`" x-model="row.factor" type="text" inputmode="decimal" class="sf-input text-base" placeholder="Jumlah">
                            <span class="text-sm text-gray-400">ke</span>
                            <select :name="`extra_conversions[${index}][to_unit_id]`" x-model="row.to_unit_id" class="sf-input text-base">
                                <option value="">Ke</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="sf-btn-danger px-3" @click="removeConversion(index)">x</button>
                    </div>
                </template>
                <button type="button" class="sf-btn-secondary w-full" @click="addConversion()">+ Add</button>
            </div>
        </div>
    </x-sf.card>

    <x-sf.card title="Distribusi Outlet">
        <div class="flex items-start justify-between gap-3 mb-4">
            <p class="text-sm text-gray-500">Pilih outlet yang menggunakan item ini. Outlet pertama yang dipilih menjadi pemilik utama legacy.</p>
            <button type="button" class="sf-btn-secondary text-xs px-3 py-2 min-h-11 shrink-0" @click="toggleAllOutlets()">
                <span x-text="isAllOutletsSelected() ? 'Kosongkan' : 'Pilih Semua'"></span>
            </button>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach($outlets as $outlet)
                <button type="button"
                        class="relative min-h-16 rounded-xl border px-3 py-3 text-left transition-colors"
                        :class="isOutletSelected({{ $outlet->id }}) ? 'sf-choice-selected' : 'sf-choice-unselected'"
                        @click="toggleOutlet({{ $outlet->id }})">
                    <span class="block text-sm">{{ $outlet->name }}</span>
                    <span x-show="primaryOutletId() === {{ $outlet->id }}" x-cloak class="badge-active mt-2">Utama</span>
                </button>
            @endforeach
        </div>
    </x-sf.card>

    <div class="fixed inset-x-0 bottom-0 z-30 bg-white/95 border-t border-gray-100 px-4 pt-3 backdrop-blur-sm lg:static lg:border-0 lg:bg-transparent lg:px-0 lg:pt-0"
         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom))">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row sm:justify-end gap-3">
            <a href="{{ $isEdit ? route('master-data.items.show', $item) : route('master-data.items.index') }}" class="sf-btn-secondary">Batal</a>
            <button type="submit" class="sf-btn-primary">Simpan Item</button>
        </div>
    </div>
</form>

@push('scripts')
<script>
    function itemForm(config) {
        return {
            sku: config.sku || '',
            name: config.name || '',
            itemType: config.itemType || 'BAHAN_BAKU',
            isActive: Boolean(config.isActive),
            trackExpiry: Boolean(config.trackExpiry),
            baseUnit: config.baseUnit || '',
            inventoryUnit: config.inventoryUnit || '',
            purchaseUnit: config.purchaseUnit || '',
            inventoryRatio: config.inventoryRatio || '',
            purchaseRatio: config.purchaseRatio || '',
            selectedDepartmentIds: config.selectedDepartmentIds || [],
            selectedOutletIds: config.selectedOutletIds || [],
            allOutletIds: config.allOutletIds || [],
            photoPreview: config.photoPreview || '',
            conversionRows: config.conversionRows || [],
            units: config.units || [],
            unitLabel(id) {
                return this.units.find((unit) => unit.id === String(id))?.label || '-';
            },
            generateSku() {
                if (this.sku.trim() !== '') return;

                const type = (this.itemType || 'ITEM').replaceAll('_', '-');
                const namePart = (this.name || 'ITEM')
                    .toUpperCase()
                    .replace(/[^A-Z0-9]+/g, '-')
                    .replace(/^-|-$/g, '')
                    .slice(0, 18);

                this.sku = `${type}-${namePart || Date.now().toString().slice(-6)}`;
            },
            setPhoto(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                this.photoPreview = URL.createObjectURL(file);
            },
            setDroppedPhoto(event) {
                const file = event.dataTransfer.files?.[0];
                if (!file) return;
                this.$refs.photoInput.files = event.dataTransfer.files;
                this.photoPreview = URL.createObjectURL(file);
            },
            toggleDepartment(id) {
                this.toggleInArray(this.selectedDepartmentIds, id);
            },
            isDepartmentSelected(id) {
                return this.selectedDepartmentIds.includes(id);
            },
            toggleOutlet(id) {
                this.toggleInArray(this.selectedOutletIds, id);
            },
            isOutletSelected(id) {
                return this.selectedOutletIds.includes(id);
            },
            primaryOutletId() {
                return this.selectedOutletIds[0] || null;
            },
            toggleAllOutlets() {
                this.selectedOutletIds = this.isAllOutletsSelected() ? [] : [...this.allOutletIds];
            },
            isAllOutletsSelected() {
                return this.allOutletIds.length > 0 && this.selectedOutletIds.length === this.allOutletIds.length;
            },
            toggleInArray(items, id) {
                const index = items.indexOf(id);
                if (index >= 0) {
                    items.splice(index, 1);
                    return;
                }
                items.push(id);
            },
            addConversion() {
                this.conversionRows.push({ from_unit_id: '', to_unit_id: '', factor: '' });
            },
            removeConversion(index) {
                this.conversionRows.splice(index, 1);
            },
            ratioSummary() {
                const base = this.unitLabel(this.baseUnit);
                const inventory = this.unitLabel(this.inventoryUnit);
                const purchase = this.unitLabel(this.purchaseUnit);
                const inventoryRatio = Number.parseFloat(String(this.inventoryRatio || '0').replace(',', '.'));
                const purchaseRatio = Number.parseFloat(String(this.purchaseRatio || '0').replace(',', '.'));

                if (this.purchaseUnit && purchaseRatio > 0) {
                    if (this.inventoryUnit && inventoryRatio > 0) {
                        const purchaseToInventory = purchaseRatio / inventoryRatio;
                        return `1 ${purchase} = ${purchaseToInventory.toLocaleString('id-ID')} ${inventory} = ${purchaseRatio.toLocaleString('id-ID')} ${base}`;
                    }

                    return `1 ${purchase} = ${purchaseRatio.toLocaleString('id-ID')} ${base}`;
                }

                if (this.inventoryUnit && inventoryRatio > 0) {
                    return `1 ${inventory} = ${inventoryRatio.toLocaleString('id-ID')} ${base}`;
                }

                return 'Pilih satuan untuk melihat ringkasan konversi.';
            },
        };
    }

    function itemAliasEditor(config) {
        return {
            storeUrl: config.storeUrl,
            aliases: config.aliases || [],
            brands: config.brands || [],
            loading: false,
            error: '',
            form: {
                brand_id: '',
                brand_sku: '',
                brand_item_name: '',
                is_primary: false,
            },
            async store() {
                this.loading = true;
                this.error = '';

                try {
                    const response = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        this.error = payload.message || 'Alias brand gagal disimpan.';
                        return;
                    }

                    if (payload.alias.is_primary) {
                        this.aliases = this.aliases.map((alias) => {
                            if (alias.brand_id === payload.alias.brand_id) {
                                return { ...alias, is_primary: false };
                            }

                            return alias;
                        });
                    }

                    this.aliases.push(payload.alias);
                    this.form = { brand_id: '', brand_sku: '', brand_item_name: '', is_primary: false };
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                } finally {
                    this.loading = false;
                }
            },
            async destroy(alias) {
                this.error = '';

                try {
                    const response = await fetch(alias.destroy_url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });

                    if (!response.ok) {
                        const payload = await response.json();
                        this.error = payload.message || 'Alias brand gagal dihapus.';
                        return;
                    }

                    this.aliases = this.aliases.filter((item) => item.id !== alias.id);
                } catch (error) {
                    this.error = 'Koneksi gagal atau data tidak dapat diproses.';
                }
            },
        };
    }
</script>
@endpush
