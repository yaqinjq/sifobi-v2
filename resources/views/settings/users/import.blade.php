@extends('layouts.app')

@section('title', 'Import User')

@section('content')
<x-sf.page-header title="Import User" subtitle="Upload Excel (sheet &quot;Import User&quot; + &quot;Nonaktifkan Akun Trial&quot;), review, baru terapkan" back="{{ route('settings.users.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-6" x-data="userImportPage()">

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        File harus punya 2 sheet dengan nama PERSIS: <strong>"Import User"</strong> (kolom Nama, Email, Telp, Password (Nokom), Role, Outlet ID, Dept ID, Status) dan <strong>"Nonaktifkan Akun Trial"</strong> (kolom ID, Email). Tidak ada yang tersimpan sampai Anda menekan "Terapkan" di langkah review.
    </div>

    {{-- Upload --}}
    <div x-show="!preview" x-cloak class="sf-card space-y-3">
        <label class="block">
            <span class="sf-label">File Excel (.xlsx / .xls / .csv)</span>
            <input type="file" accept=".xlsx,.xls,.csv" class="sf-input text-base mt-1" @change="fileSelected($event)">
        </label>
        <button type="button" class="sf-btn-primary w-full" :disabled="!file || loading" @click="doPreview()">
            <svg x-show="loading" class="w-4 h-4 animate-spin inline-block mr-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span x-text="loading ? 'Membaca file...' : 'Preview'"></span>
        </button>
        <div x-show="error" x-cloak class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="error"></div>
    </div>

    {{-- Preview --}}
    <div x-show="preview && !applied" x-cloak class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-heading font-bold text-gray-900 text-lg">Review sebelum diterapkan</h2>
            <button type="button" @click="reset()" class="text-xs text-gray-400 hover:text-gray-700">Ganti file</button>
        </div>

        {{-- Sheet 1: akun baru --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">
                    Akun Baru &mdash;
                    <span x-text="preview?.new_users?.creatable"></span> bisa dibuat,
                    <span x-text="preview?.new_users?.skip_duplicate"></span> email sudah ada (dilewati),
                    <span x-text="preview?.new_users?.error"></span> error
                </h3>
            </div>
            <div class="rounded-xl border border-gray-100 overflow-hidden overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 w-8"></th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Nama</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Email</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Role</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Password</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(row, idx) in (preview?.new_users?.rows ?? [])" :key="idx">
                            <tr :class="row.row_status === 'error' ? 'bg-red-50' : (row.row_status === 'skip_duplicate' ? 'bg-gray-50 text-gray-400' : '')">
                                <td class="px-3 py-2">
                                    <input type="checkbox" x-model="row._include" :disabled="row.row_status !== 'creatable'" class="rounded border-gray-300 text-primary-700">
                                </td>
                                <td class="px-3 py-2" x-text="row.nama"></td>
                                <td class="px-3 py-2 font-mono" x-text="row.email"></td>
                                <td class="px-3 py-2" x-text="row.role"></td>
                                <td class="px-3 py-2">
                                    <span x-text="row.password_nokom"></span>
                                    <span x-show="row.password_short_warning" class="ml-1 text-amber-600" title="Password pendek, kemungkinan nol di depan hilang -- periksa dulu">⚠</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span x-show="row.row_status === 'creatable'" class="text-green-700">Akan dibuat</span>
                                    <span x-show="row.row_status === 'skip_duplicate'" class="text-gray-400">Email sudah ada</span>
                                    <span x-show="row.row_status === 'error'" class="text-red-600" x-text="row.message"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Sheet 2: nonaktifkan akun trial --}}
        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-900">
                Nonaktifkan Akun Trial &mdash;
                <span x-text="preview?.deactivations?.will_deactivate"></span> akan dinonaktifkan,
                <span x-text="preview?.deactivations?.not_found"></span> tidak cocok,
                <span x-text="preview?.deactivations?.already_inactive"></span> sudah non-aktif
            </h3>
            <div class="rounded-xl border border-gray-100 overflow-hidden overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 w-8"></th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">ID</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Email</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Outlet</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Login Terakhir</th>
                            <th class="text-left px-3 py-2 text-gray-500 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(row, idx) in (preview?.deactivations?.rows ?? [])" :key="idx">
                            <tr :class="row.status === 'not_found' ? 'bg-red-50' : (row.status === 'already_inactive' ? 'bg-gray-50 text-gray-400' : '')">
                                <td class="px-3 py-2">
                                    <input type="checkbox" x-model="row._include" :disabled="row.status !== 'will_deactivate'" class="rounded border-gray-300 text-primary-700">
                                </td>
                                <td class="px-3 py-2" x-text="row.id"></td>
                                <td class="px-3 py-2 font-mono" x-text="row.email"></td>
                                <td class="px-3 py-2" x-text="row.outlet"></td>
                                <td class="px-3 py-2" x-text="row.login_terakhir"></td>
                                <td class="px-3 py-2">
                                    <span x-show="row.status === 'will_deactivate'" class="text-amber-700">Akan dinonaktifkan</span>
                                    <span x-show="row.status === 'not_found'" class="text-red-600">Tidak cocok dgn data</span>
                                    <span x-show="row.status === 'already_inactive'" class="text-gray-400">Sudah non-aktif</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="button" @click="reset()" class="sf-btn-secondary flex-1">Batal</button>
            <button type="button" @click="doApply()" :disabled="applying || selectedCount() === 0" class="sf-btn-primary flex-1">
                <svg x-show="applying" class="w-4 h-4 animate-spin inline-block mr-1" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="applying ? 'Menerapkan...' : 'Terapkan (' + selectedCount() + ' baris)'"></span>
            </button>
        </div>
        <div x-show="error" x-cloak class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="error"></div>
    </div>

    {{-- Hasil --}}
    <div x-show="applied" x-cloak class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-green-50 border border-green-100 px-4 py-4 text-center">
                <p class="text-2xl font-bold text-green-700" x-text="applied?.new_users?.created"></p>
                <p class="text-sm text-green-800 mt-0.5">akun baru dibuat</p>
            </div>
            <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-4 text-center">
                <p class="text-2xl font-bold text-amber-700" x-text="applied?.deactivations?.processed"></p>
                <p class="text-sm text-amber-800 mt-0.5">akun dinonaktifkan</p>
            </div>
        </div>
        <div x-show="(applied?.new_users?.errors?.length ?? 0) > 0 || (applied?.deactivations?.errors?.length ?? 0) > 0"
             class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
            <template x-for="e in (applied?.new_users?.errors ?? [])" :key="e"><p x-text="e"></p></template>
            <template x-for="e in (applied?.deactivations?.errors ?? [])" :key="e"><p x-text="e"></p></template>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('settings.users.index') }}" class="sf-btn-primary flex-1 text-center">Ke Manajemen User</a>
            <button type="button" @click="reset()" class="sf-btn-secondary flex-1">Import Lagi</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function userImportPage() {
        return {
            file: null,
            loading: false,
            applying: false,
            preview: null,
            applied: null,
            error: '',

            fileSelected(event) {
                this.file = event.target.files?.[0] ?? null;
                this.error = '';
            },

            selectedCount() {
                if (! this.preview) return 0;
                const a = (this.preview.new_users?.rows ?? []).filter(r => r._include).length;
                const b = (this.preview.deactivations?.rows ?? []).filter(r => r._include).length;
                return a + b;
            },

            async doPreview() {
                if (! this.file) return;
                this.loading = true;
                this.error = '';
                this.preview = null;

                const formData = new FormData();
                formData.append('file', this.file);

                try {
                    const res = await fetch('{{ route("settings.users.import.preview") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept: 'application/json',
                        },
                        body: formData,
                    });
                    const data = await res.json();
                    if (! res.ok) {
                        this.error = data.message || 'Gagal membaca file.';
                    } else {
                        // default centang baris yg memang bisa diproses.
                        data.new_users.rows.forEach(r => { r._include = r.row_status === 'creatable'; });
                        data.deactivations.rows.forEach(r => { r._include = r.status === 'will_deactivate'; });
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
                this.error = '';

                const newUsers = (this.preview.new_users?.rows ?? []).filter(r => r._include);
                const deactivations = (this.preview.deactivations?.rows ?? []).filter(r => r._include);

                try {
                    const res = await fetch('{{ route("settings.users.import.apply") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ new_users: newUsers, deactivations: deactivations }),
                    });
                    const data = await res.json();
                    if (! res.ok) {
                        this.error = data.message || 'Gagal menerapkan.';
                    } else {
                        this.applied = data;
                        this.preview = null;
                    }
                } catch {
                    this.error = 'Koneksi gagal. Coba lagi.';
                } finally {
                    this.applying = false;
                }
            },

            reset() {
                this.file = null;
                this.preview = null;
                this.applied = null;
                this.error = '';
            },
        };
    }
</script>
@endpush
