@extends('layouts.app')

@section('title', 'Pengaturan Sistem')

@section('content')
<x-sf.page-header title="Pengaturan Sistem" subtitle="Master konfigurasi operasional" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <x-sf.card title="Tampilan Aplikasi">
            <p class="text-sm text-gray-500 min-h-10">Kelola logo, nama aplikasi, favicon, dan identitas tampilan.</p>
            <a href="{{ route('settings.app') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
        </x-sf.card>

        @can('manage_users')
            <x-sf.card title="Manajemen User">
                <p class="text-sm text-gray-500 min-h-10">Tambah, edit, atur role, outlet, dan status akses user.</p>
                <a href="{{ route('settings.users.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
            </x-sf.card>
        @endcan

        <x-sf.card title="Jenis Bahan">
            <p class="text-sm text-gray-500 min-h-10">Kelola kategori bisnis item untuk kebutuhan Finance.</p>
            <a href="{{ route('settings.item-jenises.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
        </x-sf.card>

        <x-sf.card title="Kategori Bahan">
            <p class="text-sm text-gray-500 min-h-10">Kelola klasifikasi bahan Level 3 seperti Milk, Oil, Packaging, dan lainnya.</p>
            <a href="{{ route('settings.item-categories.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
        </x-sf.card>

        <x-sf.card title="Departemen">
            <p class="text-sm text-gray-500 min-h-10">Kelola daftar departemen operasional yang memakai item.</p>
            <a href="{{ route('settings.departments.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
        </x-sf.card>

        <x-sf.card title="Supplier">
            <p class="text-sm text-gray-500 min-h-10">Kelola daftar supplier eksternal dan internal untuk penerimaan barang.</p>
            <a href="{{ route('settings.suppliers.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
        </x-sf.card>

        @can('manage_brands_outlets')
            <x-sf.card title="Brand">
                <p class="text-sm text-gray-500 min-h-10">Kelola daftar brand, logo, dan status brand.</p>
                <a href="{{ route('settings.brands.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
            </x-sf.card>

            <x-sf.card title="Outlet">
                <p class="text-sm text-gray-500 min-h-10">Kelola outlet operasional per brand dan legal entity.</p>
                <a href="{{ route('settings.outlets.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
            </x-sf.card>
        @endcan

        @can('manage_integrations')
            <x-sf.card title="Integrasi">
                <p class="text-sm text-gray-500 min-h-10">Kelola profil integrasi eksternal seperti OMEO API.</p>
                <a href="{{ route('settings.integrations.index') }}" class="sf-btn-primary mt-4 w-full">Kelola</a>
            </x-sf.card>
        @endcan
    </div>
</div>
@endsection
