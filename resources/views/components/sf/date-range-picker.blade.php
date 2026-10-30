@props(['name' => 'date', 'from' => null, 'to' => null])

{{-- PENTING: popup kalender di-teleport ke akhir <body> (bukan dirender di
     tempat) -- komponen ini sering dipasang di dalam <x-sf.card>, dan class
     .sf-card punya overflow-hidden (buat sudut membulat & divider header)
     yang MEMOTONG apa pun yang coba "mengambang" keluar batas kartu lewat
     position:absolute. Teleport + position:fixed (dihitung dari posisi
     tombol via getBoundingClientRect()) membuat popup ini aman dipasang di
     halaman manapun, di dalam pembungkus overflow-hidden apapun, tanpa
     perlu tahu soal itu sama sekali -- lihat laporan user: kalender sempat
     3x dilaporkan rusak (CSS belum ter-build, numpuk checkbox Draft, lalu
     ini -- akar masalah sebenarnya, kepotong oleh kartu pembungkus). --}}
<div x-data="sfDateRangePicker(@js($from), @js($to))" class="relative">
    <input type="hidden" name="{{ $name }}_from" :value="appliedFrom">
    <input type="hidden" name="{{ $name }}_to" :value="appliedTo">

    <button type="button" x-ref="trigger" @click="toggle()" class="sf-input min-h-11 text-sm w-full flex items-center justify-between gap-2 text-left">
        <span x-text="displayLabel()" class="truncate"></span>
        <i class="ti ti-calendar text-gray-400 shrink-0" aria-hidden="true"></i>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak @click.outside="cancel()" @scroll.window="open = false"
             x-transition
             :style="popupStyle"
             class="fixed z-50 w-[300px] rounded-xl border border-gray-200 bg-white shadow-lg p-3">
            <div class="flex items-center justify-between mb-2">
                <button type="button" @click="prevMonth()" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-gray-100 text-gray-600">
                    <i class="ti ti-chevron-left" aria-hidden="true"></i>
                </button>
                <p class="font-semibold text-sm text-gray-900" x-text="monthLabel()"></p>
                <button type="button" @click="nextMonth()" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-gray-100 text-gray-600">
                    <i class="ti ti-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-[10px] text-gray-400 text-center mb-1">
                <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
            </div>
            <div class="grid grid-cols-7 gap-1">
                <template x-for="cell in calendarCells()" :key="cell.key">
                    <button type="button"
                            :disabled="!cell.inMonth"
                            @click="pickDay(cell)"
                            :class="dayClasses(cell)"
                            class="h-8 w-8 rounded-lg text-xs flex items-center justify-center transition-colors"
                            x-text="cell.day"></button>
                </template>
            </div>
            <p class="text-xs text-gray-500 mt-2" x-text="rangeSummary()"></p>
            <div class="flex items-center justify-end gap-2 mt-2">
                <button type="button" @click="cancel()" class="sf-btn-secondary min-h-9 px-3 text-xs">Batal</button>
                <button type="button" @click="apply()" :disabled="!from || !to" class="sf-btn-primary min-h-9 px-3 text-xs disabled:opacity-50 disabled:cursor-not-allowed">Terapkan</button>
            </div>
        </div>
    </template>
</div>

@once
<script>
function sfDateRangePicker(initialFrom, initialTo) {
    const toDateObj = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const toKey = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const anchor = initialFrom ? toDateObj(initialFrom) : new Date();

    return {
        open: false,
        popupStyle: '',
        from: initialFrom ? toDateObj(initialFrom) : null,
        to: initialTo ? toDateObj(initialTo) : null,
        appliedFrom: initialFrom,
        appliedTo: initialTo,
        viewYear: anchor.getFullYear(),
        viewMonth: anchor.getMonth(),

        toggle() {
            if (!this.open) {
                this.positionPopup();
            }
            this.open = !this.open;
        },

        // position:fixed dihitung manual dari posisi tombol -- popup ini
        // di-teleport ke <body>, jadi tidak lagi otomatis menempel ke
        // tombolnya lewat position:absolute biasa.
        positionPopup() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const popupWidth = 300;
            let left = rect.left;
            if (left + popupWidth > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - popupWidth - 8);
            }
            this.popupStyle = `top: ${rect.bottom + 4}px; left: ${left}px;`;
        },

        prevMonth() {
            this.viewMonth--;
            if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
        },
        nextMonth() {
            this.viewMonth++;
            if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
        },
        monthLabel() {
            return monthNames[this.viewMonth] + ' ' + this.viewYear;
        },

        calendarCells() {
            const firstOfMonth = new Date(this.viewYear, this.viewMonth, 1);
            const startOffset = (firstOfMonth.getDay() + 6) % 7; // Senin = 0
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < startOffset; i++) {
                cells.push({ key: 'pad-' + i, day: '', inMonth: false, date: null });
            }
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(this.viewYear, this.viewMonth, day);
                cells.push({ key: toKey(date), day, inMonth: true, date });
            }
            return cells;
        },

        pickDay(cell) {
            if (!cell.inMonth) return;
            if (!this.from || (this.from && this.to)) {
                this.from = cell.date;
                this.to = null;
            } else if (cell.date < this.from) {
                this.to = this.from;
                this.from = cell.date;
            } else {
                this.to = cell.date;
            }
        },

        dayClasses(cell) {
            if (!cell.inMonth) return 'invisible';
            const key = toKey(cell.date);
            const fromKey = this.from ? toKey(this.from) : null;
            const toKeyVal = this.to ? toKey(this.to) : null;
            if (key === fromKey || key === toKeyVal) return 'bg-primary-700 text-white font-semibold';
            if (this.from && this.to && cell.date > this.from && cell.date < this.to) return 'bg-primary-50 text-primary-800';
            return 'hover:bg-gray-100 text-gray-700';
        },

        formatDisplay(d) {
            return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
        },

        rangeSummary() {
            if (!this.from) return 'Pilih tanggal mulai';
            if (!this.to) return 'Pilih tanggal akhir';
            return this.formatDisplay(this.from) + ' – ' + this.formatDisplay(this.to);
        },

        displayLabel() {
            if (this.appliedFrom && this.appliedTo) {
                return this.formatDisplay(toDateObj(this.appliedFrom)) + ' – ' + this.formatDisplay(toDateObj(this.appliedTo));
            }
            return 'Pilih rentang tanggal';
        },

        cancel() {
            this.from = this.appliedFrom ? toDateObj(this.appliedFrom) : null;
            this.to = this.appliedTo ? toDateObj(this.appliedTo) : null;
            this.open = false;
        },

        apply() {
            if (!this.from || !this.to) return;
            this.appliedFrom = toKey(this.from);
            this.appliedTo = toKey(this.to);
            this.open = false;
            // $refs.trigger TIDAK ikut ter-teleport (cuma popup-nya yang
            // pindah ke <body>) -- jadi closest('form') harus dicari dari
            // situ, BUKAN dari this.$el (yang di dalam apply() berarti
            // elemen di dalam popup yang sudah di luar <form> aslinya).
            this.$nextTick(() => this.$refs.trigger.closest('form').submit());
        },
    };
}
</script>
@endonce
