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

    {{-- ── SEKSI TUJUAN PO ─────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </div>
            <div>
                <h2 class="font-heading font-bold text-gray-900 text-lg">Tujuan PO per Item</h2>
                <p class="text-sm text-gray-500">Export → isi kolom <code class="bg-gray-100 px-1 rounded text-xs">tujuan_po</code> → import kembali untuk update massal.</p>
            </div>
        </div>

        <x-sf.card>
            {{-- Petunjuk format --}}
            <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 mb-5">
                <p class="text-xs font-semibold text-amber-800 mb-1.5">Format kolom <code>tujuan_po</code></p>
                <div class="space-y-1">
                    @foreach([
                        ['OCIA_ROASTERY',                  'Hanya muncul di tab Roastery'],
                        ['CENTRAL_KITCHEN',                'Hanya muncul di tab Central Kitchen'],
                        ['OCIA_ROASTERY|CENTRAL_KITCHEN',  'Muncul di kedua tab'],
                        ['(kosong)',                       'Muncul di semua tab (default)'],
                    ] as [$val, $desc])
                    <div class="flex items-center gap-3 text-xs">
                        <code class="bg-white border border-amber-200 rounded px-1.5 py-0.5 text-amber-900 font-mono shrink-0">{{ $val }}</code>
                        <span class="text-amber-700">{{ $desc }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Kolom kiri: Export --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900">1 · Export data saat ini</h3>
                    <p class="text-xs text-gray-500">Download Excel berisi semua item aktif beserta tujuan PO yang sudah terdaftar. Isi atau edit kolom <code class="bg-gray-100 px-1 rounded">tujuan_po</code>, lalu import kembali.</p>
                    <a href="{{ route('master-data.ie.export.po-tags') }}"
                       class="sf-btn-secondary w-full text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                        </svg>
                        Download TujuanPO-Items.xlsx
                    </a>
                </div>

                {{-- Kolom kanan: Import dengan preview --}}
                @can('import_master_data')
                <div class="space-y-3" x-data="poTagImport()">
                    <h3 class="text-sm font-semibold text-gray-900">2 · Import & konfirmasi perubahan</h3>

                    {{-- Upload form --}}
                    <div x-show="!preview" x-cloak class="space-y-3">
                        <label class="block">
                            <span class="sf-label">File Excel yang sudah diisi (.xlsx / .csv)</span>
                            <input type="file"
                                   accept=".xlsx,.xls,.csv"
                                   class="sf-input text-base mt-1"
                                   @change="fileSelected($event)" />
                        </label>
                        <button type="button"
                                class="sf-btn-primary w-full"
                                :disabled="!file || loading"
                                @click="doPreview()">
                            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="loading ? 'Membaca file...' : 'Preview Perubahan'"></span>
                        </button>
                        <div x-show="error" x-cloak class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="error"></div>
                    </div>

                    {{-- Preview table --}}
                    <div x-show="preview && !applied" x-cloak class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                <span class="font-semibold text-gray-900" x-text="preview?.changed"></span> item akan berubah
                                <span x-show="preview?.not_found > 0" class="ml-2 text-amber-600">
                                    · <span x-text="preview?.not_found"></span> SKU tidak ditemukan
                                </span>
                            </div>
                            <button type="button" @click="reset()" class="text-xs text-gray-400 hover:text-gray-700">Ganti file</button>
                        </div>

                        <div class="rounded-xl border border-gray-100 overflow-hidden">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="text-left px-3 py-2 text-gray-500 font-semibold">SKU</th>
                                        <th class="text-left px-3 py-2 text-gray-500 font-semibold">Item</th>
                                        <th class="text-left px-3 py-2 text-gray-500 font-semibold">Sebelum</th>
                                        <th class="text-left px-3 py-2 text-gray-500 font-semibold">Sesudah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="c in preview.changes" :key="c.sku">
                                        <tr :class="c.not_found ? 'bg-red-50' : (c.has_change ? 'bg-amber-50' : '')">
                                            <td class="px-3 py-2 font-mono" x-text="c.sku"></td>
                                            <td class="px-3 py-2 text-gray-700 max-w-[120px] truncate" x-text="c.name"></td>
                                            <td class="px-3 py-2">
                                                <span x-text="tagLabel(c.old_tags)"
                                                      :class="c.has_change ? 'line-through text-gray-400' : 'text-gray-600'"></span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span x-show="c.not_found" class="text-red-500">—</span>
                                                <span x-show="!c.not_found"
                                                      x-text="tagLabel(c.new_tags)"
                                                      :class="c.has_change ? 'font-semibold text-primary-700' : 'text-gray-400'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div x-show="preview?.changed === 0" class="text-sm text-gray-500 text-center py-2">
                            Tidak ada perubahan — data sudah sama dengan yang ada di database.
                        </div>

                        <div class="flex gap-3" x-show="preview?.changed > 0">
                            <button type="button" @click="reset()" class="sf-btn-secondary flex-1">Batal</button>
                            <button type="button"
                                    @click="doApply()"
                                    :disabled="applying"
                                    class="sf-btn-primary flex-1">
                                <svg x-show="applying" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span x-text="applying ? 'Menerapkan...' : 'Terapkan ' + preview.changed + ' Perubahan'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Hasil setelah apply --}}
                    <div x-show="applied" x-cloak class="space-y-3">
                        <div class="rounded-xl bg-green-50 border border-green-100 px-4 py-4 text-center">
                            <p class="text-2xl font-bold text-green-700" x-text="applied?.updated"></p>
                            <p class="text-sm text-green-800 mt-0.5">item berhasil diperbarui</p>
                        </div>
                        <button type="button" @click="reset()" class="sf-btn-secondary w-full text-sm">Import lagi</button>
                    </div>
                </div>
                @else
                <div class="text-sm text-gray-500 flex items-center">
                    Import memerlukan permission <code class="mx-1 bg-gray-100 px-1 rounded text-xs">import_master_data</code>.
                </div>
                @endcan
            </div>
        </x-sf.card>
    </section>

</div>
@endsection

@push('scripts')
<script>
    function poTagImport() {
        return {
            file:     null,
            loading:  false,
            applying: false,
            preview:  null,
            applied:  null,
            error:    '',

            fileSelected(event) {
                this.file  = event.target.files?.[0] ?? null;
                this.error = '';
            },

            async doPreview() {
                if (! this.file) return;
                this.loading = true;
                this.error   = '';
                this.preview = null;

                const formData = new FormData();
                formData.append('file', this.file);

                try {
                    const res = await fetch('{{ route("master-data.ie.import.po-tags.preview") }}', {
                        method:  'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept:         'application/json',
                        },
                        body: formData,
                    });
                    const data = await res.json();
                    if (! res.ok) {
                        this.error = data.message || 'Gagal membaca file.';
                    } else {
                        this.preview = data;
                    }
                } catch {
                    this.error = 'Koneksi gagal. Coba lagi.';
                } finally {
                    this.loading = false;
                }
            },

            async doApply() {
                if (! this.preview) return;
                this.applying = true;
                this.error    = '';

                // Kirim hanya item yang has_change = true
                const changes = this.preview.changes.filter(c => c.has_change);

                try {
                    const res = await fetch('{{ route("master-data.ie.import.po-tags.apply") }}', {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept:         'application/json',
                        },
                        body: JSON.stringify({ changes }),
                    });
                    const data = await res.json();
                    if (! res.ok) {
                        this.error = data.message || 'Gagal menerapkan perubahan.';
                    } else {
                        this.applied  = data;
                        this.preview  = null;
                    }
                } catch {
                    this.error = 'Koneksi gagal. Coba lagi.';
                } finally {
                    this.applying = false;
                }
            },

            reset() {
                this.file    = null;
                this.preview = null;
                this.applied = null;
                this.error   = '';
            },

            tagLabel(tags) {
                if (! tags || tags.length === 0) return 'Semua tab';
                const map = { OCIA_ROASTERY: 'Roastery', CENTRAL_KITCHEN: 'Central Kitchen' };
                return tags.map(t => map[t] ?? t).join(' + ');
            },
        };
    }

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
