@extends('layouts.app')

@section('title', 'Edit Menu')

@section('content')
<x-sf.page-header
    title="Edit Menu"
    subtitle="{{ $menu->name }}"
    back="{{ route('production.menus.show', $menu) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    <x-sf.card>
        <form method="POST" action="{{ route('production.menus.update', $menu) }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ photoPreview: @js($menu->photoUrl() ?: '') }">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                <p class="sf-label mb-3">Foto Menu (opsional)</p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="w-full sm:w-32 h-32 rounded-2xl border-2 border-dashed border-gray-200 bg-white flex items-center justify-center overflow-hidden shrink-0">
                        <img x-show="photoPreview" :src="photoPreview" class="w-full h-full object-cover" alt="Preview foto menu">
                        <span x-show="!photoPreview" class="text-gray-300 text-xs text-center px-2">Belum ada foto</span>
                    </div>
                    <div class="flex-1">
                        <input type="file"
                               name="photo"
                               accept="image/png,image/jpeg,image/webp"
                               @change="photoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : photoPreview"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:min-h-11 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        <p class="text-xs text-gray-500 mt-2">JPG/PNG/WEBP, maks 2MB. Upload baru akan mengganti foto lama.</p>
                    </div>
                </div>
            </div>

            <x-sf.form-group label="Brand" for="brand_id" :required="true">
                <select id="brand_id" name="brand_id" class="sf-input text-base" required>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $menu->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Kategori Menu (opsional)" for="menu_category_id">
                <select id="menu_category_id" name="menu_category_id" class="sf-input text-base">
                    <option value="">Tanpa kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('menu_category_id', $menu->menu_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Belum ada kategori yang cocok? <a href="{{ route('settings.menu-categories.index') }}" class="text-primary-700 underline" target="_blank">Kelola kategori menu</a>.</p>
            </x-sf.form-group>

            <x-sf.form-group label="Nama Menu" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name', $menu->name) }}" class="sf-input text-base" maxlength="150" required>
            </x-sf.form-group>

            <x-sf.form-group label="Kode (opsional)" for="code">
                <input id="code" name="code" value="{{ old('code', $menu->code) }}" class="sf-input text-base" maxlength="32">
            </x-sf.form-group>

            <x-sf.form-group label="Harga Jual / POS (Rp, opsional)" for="selling_price">
                <input id="selling_price" name="selling_price" type="number" min="0" step="1" value="{{ old('selling_price', (float) $menu->selling_price) }}" class="sf-input text-base">
            </x-sf.form-group>

            @if($errors->any())
                <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit" class="sf-btn-primary w-full min-h-12">Simpan Perubahan</button>
        </form>
    </x-sf.card>
</div>
@endsection
