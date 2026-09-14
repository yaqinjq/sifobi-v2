@extends('layouts.app')

@section('title', 'Kartu Stok')

@section('content')
<x-sf.page-header title="Kartu Stok" subtitle="Saldo awal, masuk, keluar, saldo akhir per item">
    <x-slot:actions>
        @if($filters['outlet_id'] ?? null)
            <a href="{{ route('laporan.kartu-stok.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
        @endif
    </x-slot:actions>
</x-sf.page-header>

<div
    x-data="kartuStokTable(@js($rows->values()), @js($filters['date_from'] ?? $dateFrom->toDateString()), @js($filters['date_to'] ?? $dateTo->toDateString()))"
    class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4"
>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-[var(--shadow-card)] p-4">
        <form method="GET" action="{{ route('laporan.kartu-stok') }}" class="flex flex-wrap md:flex-nowrap items-center gap-2">
            <select name="outlet_id" onchange="this.form.submit()" class="sf-input text-base min-h-11 flex-1 min-w-[160px]" required @disabled($outlets->count() <= 1)>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>

            <div class="relative shrink-0" @click.outside="dateOpen = false">
                <button type="button" @click="dateOpen = !dateOpen"
                        class="sf-btn-secondary min-h-11 px-3 flex items-center gap-2 text-sm whitespace-nowrap">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                    <span x-text="rangeLabel"></span>
                </button>
                <div x-show="dateOpen" x-cloak
                     class="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-gray-200 bg-white p-3 shadow-lg space-y-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Dari</label>
                        <input type="date" name="date_from" x-model="dateFrom" class="sf-input text-sm min-h-10 w-full">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Sampai</label>
                        <input type="date" name="date_to" x-model="dateTo" class="sf-input text-sm min-h-10 w-full">
                    </div>
                    <button type="submit" class="sf-btn-primary w-full min-h-10 text-sm">Terapkan</button>
                </div>
            </div>

            <select x-model="categoryFilter" class="sf-input text-base min-h-11 flex-1 min-w-[140px]">
                <option value="">Semua kategori</option>
                <template x-for="cat in categories" :key="cat">
                    <option :value="cat" x-text="cat"></option>
                </template>
            </select>

            <div class="relative flex-1 min-w-[160px]">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                <input type="search" x-model="search" placeholder="Cari item/SKU..." class="sf-input text-base min-h-11 w-full pl-9">
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Total Item Bergerak" :value="$rows->count()" />
        <x-sf.stat label="Item Habis (Saldo 0)" :value="$rows->where('saldo_akhir', '<=', 0)->count()" />
        <x-sf.stat label="Periode" :value="$dateFrom->format('d M').' - '.$dateTo->format('d M Y')" />
    </div>

    <div class="lg:hidden space-y-3">
        <template x-for="row in filteredRows" :key="row.item_id">
            <a :href="detailUrl(row.item_id)" class="block">
                <x-sf.card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900" x-text="row.item_name"></p>
                            <p class="text-xs text-gray-500" x-text="row.canonical_sku + ' · ' + row.category_name"></p>
                        </div>
                        <span class="badge-void" x-show="parseFloat(row.saldo_akhir) <= 0">Habis</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
                        <div><span class="text-gray-500">Saldo Awal</span><br><span x-text="formatQty(row.saldo_awal) + ' ' + row.unit"></span></div>
                        <div class="text-right"><span class="text-gray-500">Saldo Akhir</span><br><span class="font-bold" :class="parseFloat(row.saldo_akhir) <= 0 ? 'text-red-600' : 'text-gray-900'" x-text="formatQty(row.saldo_akhir) + ' ' + row.unit"></span></div>
                        <div class="text-green-700" x-text="'Masuk: +' + formatQty(row.total_masuk)"></div>
                        <div class="text-right text-red-600" x-text="'Keluar: ' + formatQty(row.total_keluar)"></div>
                    </div>
                </x-sf.card>
            </a>
        </template>
        <x-sf.empty-state x-show="filteredRows.length === 0" title="Tidak ada pergerakan" description="Coba ubah pencarian/filter kategori, atau pilih outlet dan rentang tanggal lain." />
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 cursor-pointer select-none" @click="sortBy('item_name')">
                                <span class="inline-flex items-center gap-1">Item <i class="ti" :class="sortIconClass('item_name')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none" @click="sortBy('saldo_awal')">
                                <span class="inline-flex items-center gap-1">Saldo Awal <i class="ti" :class="sortIconClass('saldo_awal')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none" @click="sortBy('total_masuk')">
                                <span class="inline-flex items-center gap-1">Masuk <i class="ti" :class="sortIconClass('total_masuk')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none" @click="sortBy('total_keluar')">
                                <span class="inline-flex items-center gap-1">Keluar <i class="ti" :class="sortIconClass('total_keluar')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none" @click="sortBy('saldo_akhir')">
                                <span class="inline-flex items-center gap-1">Saldo Akhir <i class="ti" :class="sortIconClass('saldo_akhir')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none" @click="sortBy('jumlah_transaksi')">
                                <span class="inline-flex items-center gap-1">Transaksi <i class="ti" :class="sortIconClass('jumlah_transaksi')" aria-hidden="true"></i></span>
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <template x-for="row in filteredRows" :key="row.item_id">
                            <tr :class="parseFloat(row.saldo_akhir) <= 0 ? 'bg-red-50/40' : ''">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900" x-text="row.item_name"></p>
                                    <p class="text-xs text-gray-500" x-text="row.canonical_sku + ' · ' + row.category_name"></p>
                                </td>
                                <td class="px-4 py-3 text-right" x-text="formatQty(row.saldo_awal) + ' ' + row.unit"></td>
                                <td class="px-4 py-3 text-right text-green-700" x-text="'+' + formatQty(row.total_masuk)"></td>
                                <td class="px-4 py-3 text-right text-red-600" x-text="formatQty(row.total_keluar)"></td>
                                <td class="px-4 py-3 text-right font-bold" :class="parseFloat(row.saldo_akhir) <= 0 ? 'text-red-600' : 'text-gray-900'">
                                    <span x-text="formatQty(row.saldo_akhir) + ' ' + row.unit"></span>
                                    <span class="badge-void ml-1" x-show="parseFloat(row.saldo_akhir) <= 0">Habis</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500" x-text="row.jumlah_transaksi"></td>
                                <td class="px-4 py-3 text-right">
                                    <a :href="detailUrl(row.item_id)" class="text-primary-700 underline text-xs">Lihat Kartu</a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredRows.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pergerakan yang cocok dengan pencarian/filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
function kartuStokTable(rows, initialDateFrom, initialDateTo) {
    return {
        rows: rows,
        search: '',
        categoryFilter: '',
        sortKey: 'item_name',
        sortDir: 'asc',
        dateOpen: false,
        dateFrom: initialDateFrom,
        dateTo: initialDateTo,
        get rangeLabel() {
            const fmt = (value) => {
                if (!value) return '';
                const [y, m, d] = value.split('-');
                return `${d}/${m}`;
            };

            return fmt(this.dateFrom) + ' - ' + fmt(this.dateTo);
        },
        get categories() {
            return [...new Set(this.rows.map((r) => r.category_name))].sort();
        },
        get filteredRows() {
            let list = this.rows;

            if (this.search.trim() !== '') {
                const needle = this.search.trim().toLowerCase();
                list = list.filter((r) => r.item_name.toLowerCase().includes(needle) || r.canonical_sku.toLowerCase().includes(needle));
            }

            if (this.categoryFilter !== '') {
                list = list.filter((r) => r.category_name === this.categoryFilter);
            }

            const key = this.sortKey;
            const dir = this.sortDir === 'asc' ? 1 : -1;
            const numericKeys = ['saldo_awal', 'total_masuk', 'total_keluar', 'saldo_akhir', 'jumlah_transaksi'];

            return [...list].sort((a, b) => {
                let av = a[key];
                let bv = b[key];

                if (numericKeys.includes(key)) {
                    av = parseFloat(av);
                    bv = parseFloat(bv);
                } else {
                    av = String(av).toLowerCase();
                    bv = String(bv).toLowerCase();
                }

                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;

                return 0;
            });
        },
        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
        },
        sortIconClass(key) {
            if (this.sortKey !== key) {
                return 'ti-arrows-sort text-gray-300';
            }

            return this.sortDir === 'asc' ? 'ti-sort-ascending text-primary-700' : 'ti-sort-descending text-primary-700';
        },
        formatQty(value) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(parseFloat(value));
        },
        detailUrl(itemId) {
            const params = new URLSearchParams(window.location.search);

            return '{{ url('/laporan/kartu-stok') }}/' + itemId + '?' + params.toString();
        },
    };
}
</script>
@endpush
