@extends('layouts.app')

@section('title', 'Import / Export Master Data')

@section('content')
<x-sf.page-header
    title="Import / Export"
    subtitle="Master data item, satuan, mapping outlet, dan konfigurasi stok"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-6"
     x-data="importExportPage()">

    <section class="space-y-3">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-primary-50 text-primary-800 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                </svg>
            </div>
            <div>
                <h2 class="font-heading font-bold text-gray-900 text-lg">Export Data</h2>
                <p class="text-sm text-gray-500">Download data aktif sesuai tenant login.</p>
            </div>
        </div>

        @php
            $exports = [
                ['title' => 'Items / Bahan Baku', 'description' => 'SKU, kategori, unit, ratio, harga', 'route' => route('master-data.ie.export.items')],
                ['title' => 'Satuan (Units)', 'description' => 'Kode, nama, abbreviation', 'route' => route('master-data.ie.export.units')],
                ['title' => 'Konversi Satuan', 'description' => 'Konversi per item dan satuan', 'route' => route('master-data.ie.export.conversions')],
                ['title' => 'Item-Outlet Mapping', 'description' => 'Status item per outlet', 'route' => route('master-data.ie.export.item-outlets')],
                ['title' => 'Konfigurasi Min/Max Stok', 'description' => 'Min, max, reorder point', 'route' => route('master-data.ie.export.stock-configs')],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($exports as $export)
                <x-sf.card>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $export['title'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ $export['description'] }}</p>
                        </div>
                        <a href="{{ $export['route'] }}"
                           class="sf-btn-secondary text-xs px-3 py-2 min-h-11 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                            </svg>
                            Excel
                        </a>
                    </div>
                </x-sf.card>
            @endforeach
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V9m0 0l-4 4m4-4l4 4M4 7V5a2 2 0 012-2h12a2 2 0 012 2v2"/>
                </svg>
            </div>
            <div>
                <h2 class="font-heading font-bold text-gray-900 text-lg">Import Data</h2>
                <p class="text-sm text-gray-500">Gunakan template, lalu upload file Excel yang sudah diisi.</p>
            </div>
        </div>

        @can('import_master_data')
            @php
                $imports = [
                    ['key' => 'items', 'title' => 'Import Items / Bahan Baku', 'description' => 'Update by canonical SKU atau tambah item baru', 'template' => route('master-data.ie.template.items'), 'action' => route('master-data.ie.import.items')],
                    ['key' => 'units', 'title' => 'Import Satuan', 'description' => 'Update by code atau tambah unit baru', 'template' => route('master-data.ie.template.units'), 'action' => route('master-data.ie.import.units')],
                    ['key' => 'conversions', 'title' => 'Import Konversi Satuan', 'description' => 'Update by item, from unit, dan to unit', 'template' => route('master-data.ie.template.conversions'), 'action' => route('master-data.ie.import.conversions')],
                ];
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                @foreach($imports as $import)
                    <x-sf.card>
                        <form class="space-y-4"
                              @submit.prevent="submit($event, '{{ $import['key'] }}', '{{ $import['action'] }}')">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">{{ $import['title'] }}</h3>
                                <p class="text-xs text-gray-500 mt-1">{{ $import['description'] }}</p>
                            </div>

                            <a href="{{ $import['template'] }}"
                               class="sf-btn-secondary w-full text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                                </svg>
                                Download Template
                            </a>

                            <label class="block">
                                <span class="sf-label">Upload file Excel (.xlsx)</span>
                                <input type="file"
                                       name="file"
                                       accept=".xlsx,.xls,.csv"
                                       class="sf-input text-base"
                                       @change="selectFile($event, '{{ $import['key'] }}')">
                                <span class="text-xs text-gray-500 mt-2 block"
                                      x-text="selectedFile('{{ $import['key'] }}') || 'Belum ada file dipilih'"></span>
                            </label>

                            <label class="flex items-center gap-3 text-sm text-gray-600">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-700 focus:ring-primary-600" checked disabled>
                                <span>Update jika data unik sudah ada</span>
                            </label>

                            <button type="submit"
                                    class="sf-btn-primary w-full"
                                    :disabled="isLoading('{{ $import['key'] }}')">
                                <svg x-show="!isLoading('{{ $import['key'] }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V9m0 0l-4 4m4-4l4 4M4 7V5a2 2 0 012-2h12a2 2 0 012 2v2"/>
                                </svg>
                                <svg x-show="isLoading('{{ $import['key'] }}')" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span x-text="isLoading('{{ $import['key'] }}') ? 'Memproses...' : 'Upload & Proses'"></span>
                            </button>

                            <div x-show="result('{{ $import['key'] }}')"
                                 x-cloak
                                 class="rounded-xl border border-green-100 bg-green-50 p-3 text-sm text-green-900">
                                <p class="font-semibold">Import selesai</p>
                                <div class="grid grid-cols-3 gap-2 mt-2 text-center">
                                    <div class="rounded-lg bg-white p-2">
                                        <p class="text-xs text-gray-500">Berhasil</p>
                                        <p class="font-bold" x-text="result('{{ $import['key'] }}')?.inserted ?? 0"></p>
                                    </div>
                                    <div class="rounded-lg bg-white p-2">
                                        <p class="text-xs text-gray-500">Update</p>
                                        <p class="font-bold" x-text="result('{{ $import['key'] }}')?.updated ?? 0"></p>
                                    </div>
                                    <div class="rounded-lg bg-white p-2">
                                        <p class="text-xs text-gray-500">Gagal</p>
                                        <p class="font-bold" x-text="result('{{ $import['key'] }}')?.failed ?? 0"></p>
                                    </div>
                                </div>
                                <template x-if="result('{{ $import['key'] }}')?.errors?.length">
                                    <div class="mt-3">
                                        <p class="font-semibold text-red-700">Detail Error</p>
                                        <ul class="mt-1 space-y-1 text-red-700">
                                            <template x-for="error in result('{{ $import['key'] }}').errors" :key="`${error.row}-${error.message}`">
                                                <li x-text="`Baris ${error.row}: ${error.message}`"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </form>
                    </x-sf.card>
                @endforeach
            </div>
        @else
            <x-sf.card>
                <p class="text-sm text-gray-600">
                    Akun ini hanya memiliki akses export. Import master data dibatasi untuk Admin dan Finance Manager.
                </p>
            </x-sf.card>
        @endcan
    </section>
</div>
@endsection

@push('scripts')
<script>
    function importExportPage() {
        return {
            files: {},
            loading: {},
            results: {},
            selectFile(event, key) {
                this.files[key] = event.target.files?.[0]?.name || '';
                this.results[key] = null;
            },
            selectedFile(key) {
                return this.files[key] || '';
            },
            isLoading(key) {
                return Boolean(this.loading[key]);
            },
            result(key) {
                return this.results[key] || null;
            },
            async submit(event, key, url) {
                const form = event.target;
                const formData = new FormData(form);

                this.loading[key] = true;
                this.results[key] = null;

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        this.results[key] = {
                            inserted: 0,
                            updated: 0,
                            failed: 1,
                            errors: [{ row: 0, message: payload.message || 'Upload gagal diproses.' }],
                        };

                        return;
                    }

                    this.results[key] = payload;
                    form.reset();
                    this.files[key] = '';
                } catch (error) {
                    this.results[key] = {
                        inserted: 0,
                        updated: 0,
                        failed: 1,
                        errors: [{ row: 0, message: 'Koneksi gagal atau file tidak dapat diproses.' }],
                    };
                } finally {
                    this.loading[key] = false;
                }
            },
        };
    }
</script>
@endpush
