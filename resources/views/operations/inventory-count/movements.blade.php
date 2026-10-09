@extends('layouts.app')

@section('title', 'Rekap Mingguan')

@section('content')
<x-sf.page-header title="Rekap Mingguan" subtitle="{{ $template->name }} -- {{ $outlet?->name }} / {{ $department?->name }}" back="{{ route('operations.inventory-count.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <strong>Saldo mandiri Inventory Simple -- BUKAN saldo stok sistem resmi.</strong>
        Dihitung dari saldo awal + IN &minus; OUT yang diisi di sini saja, terpisah total dari form hitung fisik harian dan dari saldo stok yang dipakai Opname/POS/Purchase Order.
    </div>

    <x-sf.card>
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('operations.inventory-count.movements.index', array_filter(['template_id' => $template->id, 'week' => $weekStart->copy()->subDays(7)->toDateString(), 'outlet_id' => $isSuperAdmin ? $outlet?->id : null, 'department_id' => $isSuperAdmin ? $department?->id : null])) }}"
               class="sf-btn-secondary min-h-10 px-3 text-sm"><i class="ti ti-chevron-left" aria-hidden="true"></i> Minggu Lalu</a>
            <div class="text-center">
                <p class="text-xs text-gray-500">Minggu</p>
                <p class="font-semibold text-gray-900">{{ $weekStart->translatedFormat('d M') }} &ndash; {{ $weekStart->copy()->addDays(6)->translatedFormat('d M Y') }}</p>
            </div>
            <a href="{{ route('operations.inventory-count.movements.index', array_filter(['template_id' => $template->id, 'week' => $weekStart->copy()->addDays(7)->toDateString(), 'outlet_id' => $isSuperAdmin ? $outlet?->id : null, 'department_id' => $isSuperAdmin ? $department?->id : null])) }}"
               class="sf-btn-secondary min-h-10 px-3 text-sm">Minggu Depan <i class="ti ti-chevron-right" aria-hidden="true"></i></a>
        </div>
    </x-sf.card>

    <div class="overflow-x-auto rounded-xl border border-gray-200 -mx-4 lg:mx-0">
        <table class="min-w-full text-xs border-collapse" x-data="{ weekStart: @js($weekStart->toDateString()) }">
            <thead>
                <tr class="bg-gray-50">
                    <th rowspan="2" class="sticky left-0 z-20 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-600 w-[180px] align-bottom">Item</th>
                    <th rowspan="2" class="sticky left-[180px] z-20 bg-gray-50 px-2 py-2 text-center font-semibold text-gray-600 min-w-[90px] align-bottom">Saldo Awal<br><span class="font-normal text-gray-400">({{ $weekStart->translatedFormat('d/m') }})</span></th>
                    @for($i = 0; $i < 7; $i++)
                        @php $d = $weekStart->copy()->addDays($i); @endphp
                        <th colspan="3" class="px-2 py-1.5 text-center font-semibold text-gray-600 border-l border-gray-200 whitespace-nowrap">{{ $d->translatedFormat('D, d/m') }}</th>
                    @endfor
                </tr>
                <tr class="bg-gray-50">
                    @for($i = 0; $i < 7; $i++)
                        <th class="px-1.5 py-1.5 text-center font-medium text-gray-500 border-l border-gray-200 min-w-[64px]">IN</th>
                        <th class="px-1.5 py-1.5 text-center font-medium text-gray-500 min-w-[64px]">OUT</th>
                        <th class="px-1.5 py-1.5 text-center font-medium text-gray-500 min-w-[72px]">BAL</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach($rows->groupBy('subcategory') as $subcategory => $groupRows)
                    <tr class="bg-primary-50">
                        <td colspan="23" class="sticky left-0 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary-800 bg-primary-50">{{ $subcategory ?: 'Lainnya' }}</td>
                    </tr>
                    @foreach($groupRows as $row)
                        @include('operations.inventory-count.partials.movement-row', ['row' => $row, 'weekStart' => $weekStart])
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 leading-relaxed">
        Kosong pada IN/OUT dihitung sebagai 0 saat menghitung saldo, TAPI bukan bukti hari itu sudah dicatat -- isi eksplisit 0 kalau memang tidak ada pergerakan. Saldo awal minggu yang tidak diisi akan mengambil saldo akhir minggu sebelumnya secara otomatis; kalau tidak ada saldo awal sama sekali di riwayat manapun, kolom BAL akan menampilkan pesan "saldo awal belum tersedia", bukan 0.
    </p>
</div>
@endsection

@push('scripts')
<script>
function movementRow(config) {
    return {
        itemId: config.itemId,
        autosaveUrl: config.autosaveUrl,
        openingBalanceUrl: config.openingBalanceUrl,
        weekStartDate: config.weekStartDate,
        openingBalance: config.openingBalance,
        openingBalanceInput: config.openingBalance ?? '',
        days: config.days,
        error: config.error,
        savingOpening: false,
        savingDay: null,
        async saveOpeningBalance() {
            if (this.openingBalanceInput.trim() === '') return;
            this.savingOpening = true;
            try {
                const response = await fetch(this.openingBalanceUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ week_start: this.weekStartDate, qty: this.openingBalanceInput }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this.applyWeek(data.week);
            } finally {
                this.savingOpening = false;
            }
        },
        async saveDay(index) {
            const day = this.days[index];
            this.savingDay = index;
            try {
                const response = await fetch(this.autosaveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ date: day.date, qty_in: day.qty_in, qty_out: day.qty_out }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this.applyWeek(data.week);
            } finally {
                this.savingDay = null;
            }
        },
        applyWeek(week) {
            this.error = week.error;
            this.days = week.days.length ? week.days : this.days;
            if (week.base !== null && week.base !== undefined) {
                this.openingBalance = week.base;
            }
        },
        formatBalance(value) {
            if (value === null || value === undefined) return null;
            // Tampilan ringkas (buang nol di belakang) -- nilai ASLI yang
            // dikirim ke server tetap presisi 6 desimal, ini cuma tampilan.
            const n = parseFloat(value);
            return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        },
    };
}
</script>
@endpush
