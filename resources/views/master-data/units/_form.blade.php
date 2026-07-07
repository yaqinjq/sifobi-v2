@php
    $isEdit = $unit->exists;
@endphp

<form method="POST" action="{{ $action }}" class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <x-sf.card title="Detail Satuan">
        <div class="space-y-4">
            <x-sf.form-group label="Nama Satuan" for="name" :required="true">
                <input id="name"
                       name="name"
                       type="text"
                       value="{{ old('name', $unit->name) }}"
                       required
                       maxlength="100"
                       class="sf-input text-base">
            </x-sf.form-group>

            <x-sf.form-group
                label="Kode"
                for="code"
                :required="true"
                hint="Huruf kapital, tanpa spasi. Contoh: GR, ML, PCS">
                <input id="code"
                       name="code"
                       type="text"
                       value="{{ old('code', $unit->code) }}"
                       required
                       maxlength="20"
                       autocapitalize="characters"
                       class="sf-input text-base uppercase">
            </x-sf.form-group>

            <x-sf.form-group
                label="Singkatan Tampilan"
                for="abbreviation"
                :required="true"
                hint="Contoh: gr, ml, pcs, sachet">
                <input id="abbreviation"
                       name="abbreviation"
                       type="text"
                       value="{{ old('abbreviation', $unit->abbreviation) }}"
                       required
                       maxlength="20"
                       class="sf-input text-base">
            </x-sf.form-group>
        </div>
    </x-sf.card>

    <div class="mt-5 flex flex-col sm:flex-row sm:justify-end gap-3">
        <a href="{{ route('master-data.units.index') }}" class="sf-btn-secondary">Batal</a>
        <button type="submit" class="sf-btn-primary sm:w-auto">Simpan</button>
    </div>
</form>
