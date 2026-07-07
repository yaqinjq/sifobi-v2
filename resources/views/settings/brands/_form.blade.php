@php
    $isEdit = $brand->exists;
    $logoUrl = $brand->logo_path ? asset('storage/'.$brand->logo_path) : null;
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Data Brand">
        <div class="grid grid-cols-1 md:grid-cols-[180px_1fr] gap-5">
            <div>
                <div class="aspect-square rounded-2xl border border-gray-100 bg-gray-50 overflow-hidden flex items-center justify-center text-sm font-semibold text-gray-400">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $brand->name }}" class="h-full w-full object-cover">
                    @else
                        LOGO
                    @endif
                </div>
                <input type="file" name="logo" accept="image/jpeg,image/png" class="sf-input text-base mt-3">
                <p class="text-xs text-gray-500 mt-2">JPG/PNG, maksimal 2MB.</p>
            </div>

            <div class="space-y-4">
                <x-sf.form-group label="Kode Brand" for="code" :required="true">
                    <input id="code" name="code" value="{{ old('code', $brand->code) }}" class="sf-input text-base uppercase" maxlength="32" required>
                </x-sf.form-group>

                <x-sf.form-group label="Nama Brand" for="name" :required="true">
                    <input id="name" name="name" value="{{ old('name', $brand->name) }}" class="sf-input text-base" maxlength="255" required>
                </x-sf.form-group>

                <x-sf.form-group label="Group" for="group_id">
                    <select id="group_id" name="group_id" class="sf-input text-base">
                        <option value="">Tanpa group</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected(old('group_id', $brand->group_id) == $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-sf.form-group>

                <x-sf.form-group label="Deskripsi" for="description">
                    <textarea id="description" name="description" rows="3" class="sf-input text-base resize-none">{{ old('description', $brand->description) }}</textarea>
                </x-sf.form-group>

                <x-sf.form-group label="Status" for="status" :required="true">
                    <select id="status" name="status" class="sf-input text-base" required>
                        <option value="ACTIVE" @selected(old('status', $brand->status ?? 'ACTIVE') === 'ACTIVE')>Aktif</option>
                        <option value="INACTIVE" @selected(old('status', $brand->status) === 'INACTIVE')>Non-Aktif</option>
                    </select>
                </x-sf.form-group>
            </div>
        </div>
    </x-sf.card>

    <div class="flex flex-col sm:flex-row sm:justify-end gap-3">
        <a href="{{ route('settings.brands.index') }}" class="sf-btn-secondary">Batal</a>
        <button type="submit" class="sf-btn-primary">Simpan Brand</button>
    </div>
</form>
