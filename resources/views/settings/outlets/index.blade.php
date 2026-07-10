@extends('layouts.app')

@section('title', 'Pengaturan Outlet')

@section('content')
<x-sf.page-header title="Pengaturan Outlet" subtitle="Kelola outlet per brand" back="{{ route('settings.index') }}">
    <x-slot:actions>
        <a href="{{ route('settings.outlets.create') }}" class="sf-btn-primary inline-flex min-h-11 items-center justify-center gap-2 text-xs px-3 py-2">
            <i class="ti ti-plus text-base" aria-hidden="true"></i>
            <span>Outlet</span>
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <form method="GET" action="{{ route('settings.outlets.index') }}" class="sf-card p-4">
        <x-sf.form-group label="Filter Brand" for="brand_id">
            <select id="brand_id" name="brand_id" class="sf-input text-base" onchange="this.form.submit()">
                <option value="">Semua brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected($selectedBrandId === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
        </x-sf.form-group>
    </form>

    <div class="lg:hidden space-y-3">
        @foreach($outlets as $outlet)
            <a href="{{ route('settings.outlets.edit', $outlet) }}" class="block sf-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ $outlet->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $outlet->code }} - {{ $outlet->brand?->name }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $outlet->address ?: 'Alamat belum diisi' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="{{ $outlet->status === 'ACTIVE' ? 'badge-active' : 'badge-inactive' }}">{{ $outlet->status }}</span>
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600" title="Edit {{ $outlet->name }}">
                            <i class="ti ti-edit text-base" aria-hidden="true"></i>
                        </span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="hidden lg:block sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Brand</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">PT</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kontak</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($outlets as $outlet)
                        <tr class="odd:bg-white even:bg-gray-50/60">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $outlet->code }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                <p class="font-semibold text-gray-900">{{ $outlet->name }}</p>
                                <p class="text-xs text-gray-400">{{ $outlet->address ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $outlet->brand?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $outlet->legalEntity?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $outlet->contact_phone ?: '-' }}</td>
                            <td class="px-4 py-3"><span class="{{ $outlet->status === 'ACTIVE' ? 'badge-active' : 'badge-inactive' }}">{{ $outlet->status }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <x-icon-btn
                                    icon="edit"
                                    label="Edit {{ $outlet->name }}"
                                    color="blue"
                                    size="sm"
                                    href="{{ route('settings.outlets.edit', $outlet) }}"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
