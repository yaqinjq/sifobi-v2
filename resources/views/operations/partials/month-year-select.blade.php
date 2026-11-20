{{--
    Pengganti <input type="month">: Firefox & Safari desktop tidak mendukung
    type="month" (jatuh jadi kotak teks biasa, harus diketik "2026-11"),
    sehingga pemilih bulan "kadang muncul kadang tidak" tergantung browser.
    Dua dropdown ini menulis nilai YYYY-MM yang sama ke properti `month`
    milik komponen Alpine induk.
--}}
@php
    $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $currentYear = (int) now()->format('Y');
@endphp
<div x-data="{ mm: '', yy: '{{ $currentYear }}' }"
     x-effect="month = mm ? `${yy}-${mm}` : ''">
    <label class="sf-label">Bulan</label>
    <div class="grid grid-cols-2 gap-2">
        <select x-model="mm" class="sf-input text-base min-h-11">
            <option value="">Pilih bulan</option>
            @foreach($monthNames as $index => $monthName)
                <option value="{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}">{{ $monthName }}</option>
            @endforeach
        </select>
        <select x-model="yy" class="sf-input text-base min-h-11">
            @foreach(range($currentYear - 2, $currentYear + 1) as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>
    </div>
</div>
