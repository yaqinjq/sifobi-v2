@extends('layouts.app')

@section('title', 'Import Opname Bulanan')

@section('content')
<x-sf.page-header title="Import Opname Bulanan" subtitle="Isi lewat template Excel, bukan satu-satu" back="{{ route('operations.opname.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4" x-data="opnameMonthlyImport()">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Begitu 1 tanggal dicentang & diproses, sesi opname tanggal itu <strong>langsung disetujui</strong> dan saldo stok langsung disesuaikan -- tidak ada approve manual terpisah sesudahnya. Proses penyimpanan berjalan di belakang layar, pantau progressnya di bawah.
    </div>

    <template x-if="step === 'upload'">
        <x-sf.card title="1. Pilih Konteks & Download Template">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="sf-label">Outlet</label>
                    <select x-model="outletId" class="sf-input text-base min-h-11">
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Departemen</label>
                    <select x-model="departmentId" class="sf-input text-base min-h-11">
                        <option value="">Pilih departemen</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Bulan</label>
                    <input type="month" x-model="month" class="sf-input text-base min-h-11">
                </div>
            </div>
            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                <button type="button" @click="downloadTemplate()" :disabled="!canDownload" class="sf-btn-secondary min-h-11 px-4 disabled:opacity-50">Download Template</button>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-4">
                <label class="sf-label">2. Upload Template yang Sudah Diisi</label>
                <input type="file" x-ref="fileInput" accept=".xlsx,.xls,.csv" class="sf-input text-base min-h-11">
                <button type="button" @click="doPreview()" :disabled="!canDownload || loading" class="sf-btn-primary min-h-11 px-4 mt-3 disabled:opacity-50">
                    <span x-show="!loading">Preview</span>
                    <span x-show="loading" x-cloak>Memproses...</span>
                </button>
                <p class="text-sm text-red-600 mt-2" x-show="error" x-text="error"></p>
            </div>
        </x-sf.card>
    </template>

    <template x-if="step === 'preview'">
        <div class="space-y-4">
            <x-sf.card>
                <div class="flex items-center justify-between gap-3">
                    <button type="button" @click="prevWeek()" class="sf-btn-secondary min-h-10 px-3 text-sm">&larr; Minggu Lalu</button>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">Minggu</p>
                        <p class="font-semibold text-gray-900" x-text="weekLabel()"></p>
                    </div>
                    <button type="button" @click="nextWeek()" class="sf-btn-secondary min-h-10 px-3 text-sm">Minggu Depan &rarr;</button>
                </div>
            </x-sf.card>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-600">Item</th>
                            <template x-for="date in weekDates()" :key="date">
                                <th class="px-2 py-2 text-center font-semibold text-gray-600 border-l border-gray-200">
                                    <div x-text="date"></div>
                                    <template x-if="dateRowStatus(date) !== 'skip_locked'">
                                        <label class="flex items-center justify-center gap-1 mt-1 font-normal">
                                            <input type="checkbox" :checked="selectedDates[date]" @change="toggleDate(date, $event.target.checked)">
                                            <span x-text="dateRowStatus(date) === 'update_draft' ? 'Timpa draft' : 'Baru'"></span>
                                        </label>
                                    </template>
                                    <template x-if="dateRowStatus(date) === 'skip_locked'">
                                        <span class="text-gray-400 block mt-1">Sudah final</span>
                                    </template>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="itemName in itemNamesInWeek()" :key="itemName">
                            <tr class="border-t border-gray-100">
                                <td class="sticky left-0 bg-white px-3 py-1.5 font-medium text-gray-800" x-text="itemName"></td>
                                <template x-for="date in weekDates()" :key="date">
                                    <td class="px-2 py-1.5 text-center border-l border-gray-100" x-text="qtyFor(date, itemName)"></td>
                                </template>
                            </tr>
                        </template>
                        <tr x-show="itemNamesInWeek().length === 0">
                            <td class="px-3 py-6 text-center text-gray-400" :colspan="weekDates().length + 1">Tidak ada data terisi minggu ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" @click="step = 'upload'" class="sf-btn-secondary min-h-11 px-4">Upload Ulang</button>
                <button type="button" @click="doApply()" :disabled="selectedCount() === 0 || loading" class="sf-btn-primary min-h-11 px-4 disabled:opacity-50">
                    Terapkan (<span x-text="selectedCount()"></span> tanggal)
                </button>
            </div>
            <p class="text-sm text-red-600" x-show="error" x-text="error"></p>
        </div>
    </template>

    <template x-if="step === 'processing'">
        <x-sf.card title="Memproses...">
            <div class="space-y-3">
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="bg-primary-600 h-3 rounded-full transition-all" :style="`width: ${progressPct()}%`"></div>
                </div>
                <p class="text-sm text-gray-600">
                    <span x-text="run.processed_units + run.failed_units"></span> / <span x-text="run.total_units"></span> tanggal diproses.
                    Status: <span x-text="run.status"></span>
                </p>
                <template x-if="run.errors && run.errors.length">
                    <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold mb-1">Error ({{ '' }}<span x-text="run.errors.length"></span>):</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <template x-for="(err, idx) in run.errors.slice(0, 10)" :key="idx">
                                <li x-text="err"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <template x-if="run.status === 'DONE' || run.status === 'FAILED'">
                    <a href="{{ route('operations.opname.index') }}" class="sf-btn-primary min-h-11 px-4 inline-block">Selesai, Lihat Daftar Opname</a>
                </template>
            </div>
        </x-sf.card>
    </template>
</div>

@push('scripts')
<script>
function opnameMonthlyImport() {
    return {
        step: 'upload',
        outletId: '',
        departmentId: '',
        month: '',
        loading: false,
        error: '',
        rows: [],
        selectedDates: {},
        weekOffset: 0,
        token: null,
        runId: null,
        run: { status: '', total_units: 0, processed_units: 0, failed_units: 0, errors: [] },
        get canDownload() {
            return this.outletId && this.departmentId && this.month;
        },
        downloadTemplate() {
            const params = new URLSearchParams({ outlet_id: this.outletId, department_id: this.departmentId, month: this.month });
            window.location.href = @js(route('operations.opname.import-bulanan.template')) + '?' + params.toString();
        },
        async doPreview() {
            const file = this.$refs.fileInput.files[0];
            if (!file) { this.error = 'Pilih file terlebih dahulu.'; return; }
            this.loading = true;
            this.error = '';
            const formData = new FormData();
            formData.append('file', file);
            formData.append('outlet_id', this.outletId);
            formData.append('department_id', this.departmentId);
            formData.append('month', this.month);
            try {
                const response = await fetch(@js(route('operations.opname.import-bulanan.preview')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' },
                    body: formData,
                });
                const data = await response.json();
                if (!response.ok) { this.error = data.message || 'Gagal memproses file.'; this.loading = false; return; }
                this.token = data.token;
                this.rows = data.rows;
                this.selectedDates = {};
                this.rows.forEach(row => { if (row.row_status !== 'skip_locked') this.selectedDates[row.date] = true; });
                this.weekOffset = 0;
                this.step = 'preview';
            } catch (e) {
                this.error = 'Gagal menghubungi server.';
            }
            this.loading = false;
        },
        monthStart() {
            return new Date(this.month + '-01T00:00:00');
        },
        weekDates() {
            const start = this.monthStart();
            start.setDate(start.getDate() + this.weekOffset * 7);
            const dates = [];
            for (let i = 0; i < 7; i++) {
                const d = new Date(start);
                d.setDate(start.getDate() + i);
                if (d.getMonth() !== this.monthStart().getMonth()) break;
                dates.push(d.toISOString().slice(0, 10));
            }
            return dates;
        },
        weekLabel() {
            const dates = this.weekDates();
            return dates.length ? `${dates[0]} - ${dates[dates.length - 1]}` : '-';
        },
        prevWeek() { if (this.weekOffset > 0) this.weekOffset--; },
        nextWeek() {
            const next = (this.weekOffset + 1) * 7;
            if (next < new Date(this.monthStart().getFullYear(), this.monthStart().getMonth() + 1, 0).getDate()) this.weekOffset++;
        },
        rowForDate(date) {
            return this.rows.find(r => r.date === date);
        },
        dateRowStatus(date) {
            return this.rowForDate(date)?.row_status || 'new_session';
        },
        itemNamesInWeek() {
            const names = new Set();
            this.weekDates().forEach(date => {
                const row = this.rowForDate(date);
                if (row) row.items.forEach(item => names.add(item.item_name));
            });
            return Array.from(names).sort();
        },
        qtyFor(date, itemName) {
            const row = this.rowForDate(date);
            if (!row) return '';
            const item = row.items.find(i => i.item_name === itemName);
            return item ? `${item.qty} ${item.satuan}` : '';
        },
        toggleDate(date, checked) {
            this.selectedDates[date] = checked;
        },
        selectedCount() {
            return Object.values(this.selectedDates).filter(Boolean).length;
        },
        async doApply() {
            this.loading = true;
            this.error = '';
            const selected = Object.keys(this.selectedDates).filter(d => this.selectedDates[d]);
            try {
                const response = await fetch(@js(route('operations.opname.import-bulanan.apply')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ token: this.token, selected_dates: selected }),
                });
                const data = await response.json();
                if (!response.ok) { this.error = data.message || 'Gagal memulai proses.'; this.loading = false; return; }
                this.runId = data.run_id;
                this.step = 'processing';
                this.pollStatus();
            } catch (e) {
                this.error = 'Gagal menghubungi server.';
            }
            this.loading = false;
        },
        async pollStatus() {
            const statusUrl = @js(route('operations.opname.import-bulanan.apply.status', ['run' => '__RUN__']));
            const url = statusUrl.replace('__RUN__', this.runId);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            this.run = await response.json();
            if (this.run.status === 'DONE' || this.run.status === 'FAILED') return;
            setTimeout(() => this.pollStatus(), 3000);
        },
        progressPct() {
            if (!this.run.total_units) return 0;
            return Math.round(((this.run.processed_units + this.run.failed_units) / this.run.total_units) * 100);
        },
    };
}
</script>
@endpush
@endsection
