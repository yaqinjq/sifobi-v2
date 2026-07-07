@php($isEdit = $outlet->exists)

<form method="POST" action="{{ $action }}" class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Data Outlet">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-sf.form-group label="Kode Outlet" for="code" :required="true">
                <input id="code" name="code" value="{{ old('code', $outlet->code) }}" class="sf-input text-base uppercase" maxlength="32" required>
            </x-sf.form-group>

            <x-sf.form-group label="Nama Outlet" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name', $outlet->name) }}" class="sf-input text-base" maxlength="255" required>
            </x-sf.form-group>

            <x-sf.form-group label="Brand" for="brand_id" :required="true">
                <select id="brand_id" name="brand_id" class="sf-input text-base" required>
                    <option value="">Pilih brand</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $outlet->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Legal Entity" for="legal_entity_id" :required="true">
                <select id="legal_entity_id" name="legal_entity_id" class="sf-input text-base" required>
                    <option value="">Pilih PT</option>
                    @foreach($legalEntities as $legalEntity)
                        <option value="{{ $legalEntity->id }}" @selected(old('legal_entity_id', $outlet->legal_entity_id) == $legalEntity->id)>{{ $legalEntity->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Kontak" for="contact_phone">
                <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $outlet->contact_phone) }}" class="sf-input text-base" maxlength="50">
            </x-sf.form-group>

            <x-sf.form-group label="Status" for="status" :required="true">
                <select id="status" name="status" class="sf-input text-base" required>
                    <option value="ACTIVE" @selected(old('status', $outlet->status ?? 'ACTIVE') === 'ACTIVE')>Aktif</option>
                    <option value="INACTIVE" @selected(old('status', $outlet->status) === 'INACTIVE')>Non-Aktif</option>
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Tipe Outlet" for="outlet_type">
                <input id="outlet_type" name="outlet_type" value="{{ old('outlet_type', $outlet->outlet_type ?? 'OUTLET') }}" class="sf-input text-base" maxlength="32">
            </x-sf.form-group>

            <x-sf.form-group label="Timezone" for="timezone">
                <input id="timezone" name="timezone" value="{{ old('timezone', $outlet->timezone ?? 'Asia/Jakarta') }}" class="sf-input text-base" maxlength="64">
            </x-sf.form-group>
        </div>

        <div class="mt-4">
            <x-sf.form-group label="Alamat" for="address">
                <textarea id="address" name="address" rows="3" class="sf-input text-base resize-none">{{ old('address', $outlet->address) }}</textarea>
            </x-sf.form-group>
        </div>
    </x-sf.card>

    <div class="flex flex-col sm:flex-row sm:justify-end gap-3">
        <a href="{{ route('settings.outlets.index') }}" class="sf-btn-secondary">Batal</a>
        <button type="submit" class="sf-btn-primary">Simpan Outlet</button>
    </div>
</form>
