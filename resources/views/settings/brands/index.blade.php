@extends('layouts.app')

@section('title', 'Pengaturan Brand')

@section('content')
<x-sf.page-header title="Pengaturan Brand" subtitle="Kelola brand dalam tenant" back="{{ route('settings.index') }}">
    <x-slot:actions>
        <a href="{{ route('settings.brands.create') }}" class="sf-btn-primary inline-flex min-h-11 items-center justify-center gap-2 text-xs px-3 py-2">
            <i class="ti ti-plus text-base" aria-hidden="true"></i>
            <span>Brand</span>
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    <div class="lg:hidden space-y-3">
        @foreach($brands as $brand)
            <article class="sf-card p-4">
                <div class="flex gap-3">
                    <div class="h-12 w-12 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center text-xs font-semibold text-gray-400">
                        @if($brand->logo_path)
                            <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" class="h-full w-full object-cover">
                        @else
                            {{ substr($brand->code, 0, 2) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-gray-900">{{ $brand->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $brand->code }} - {{ $brand->outlets_count }} outlet</p>
                    </div>
                    <span class="{{ $brand->status === 'ACTIVE' ? 'badge-active' : 'badge-inactive' }}">{{ $brand->status }}</span>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <x-icon-btn
                        icon="edit"
                        label="Edit {{ $brand->name }}"
                        color="blue"
                        href="{{ route('settings.brands.edit', $brand) }}"
                    />
                    <x-icon-btn
                        icon="delete"
                        label="Hapus Brand"
                        color="red"
                        href="{{ route('settings.brands.destroy', $brand) }}"
                        method="DELETE"
                        confirm="Yakin hapus brand ini? Pastikan tidak ada outlet aktif."
                    />
                </div>
            </article>
        @endforeach
    </div>

    <div class="hidden lg:block sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Logo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($brands as $brand)
                        <tr class="odd:bg-white even:bg-gray-50/60">
                            <td class="px-4 py-3">
                                <div class="h-10 w-10 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center text-xs font-semibold text-gray-400">
                                    @if($brand->logo_path)
                                        <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" class="h-full w-full object-cover">
                                    @else
                                        {{ substr($brand->code, 0, 2) }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $brand->code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $brand->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $brand->outlets_count }}</td>
                            <td class="px-4 py-3"><span class="{{ $brand->status === 'ACTIVE' ? 'badge-active' : 'badge-inactive' }}">{{ $brand->status }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-btn
                                        icon="edit"
                                        label="Edit {{ $brand->name }}"
                                        color="blue"
                                        size="sm"
                                        href="{{ route('settings.brands.edit', $brand) }}"
                                    />
                                    <x-icon-btn
                                        icon="delete"
                                        label="Hapus Brand"
                                        color="red"
                                        size="sm"
                                        href="{{ route('settings.brands.destroy', $brand) }}"
                                        method="DELETE"
                                        confirm="Yakin hapus brand ini? Pastikan tidak ada outlet aktif."
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
