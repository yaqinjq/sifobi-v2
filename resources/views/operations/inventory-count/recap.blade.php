@extends('layouts.app')

@php $isOpnameRecap = $isOpnameRecap ?? false; @endphp

@section('title', 'Rekap Hasil Hitung Mingguan')

@section('content')
<x-sf.page-header title="Rekap Hasil Hitung Mingguan{{ $isOpnameRecap ? ' (Data Opname)' : '' }}" subtitle="{{ $isOpnameRecap ? 'Opname' : $template->name }} -- {{ $outlet?->name }} / {{ $department?->name ?? 'Semua Departemen' }}" back="{{ $isOpnameRecap ? route('operations.inventory-count.opname-history') : route('operations.inventory-count.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4"
     x-data
     x-init="Alpine.store('recap', { search: '', categoryFilter: '' })">
    @php $includeDraft = $includeDraft ?? false; @endphp
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        @if($includeDraft)
            <strong>Ikut menampilkan sesi yang masih Draft (belum dikirim).</strong>
            Angka berwarna <span class="text-amber-700 font-semibold">kuning</span> di tabel bawah ini masih Draft, BELUM final -- bisa saja masih berubah. Angka hitam adalah hasil yang sudah Terkirim (final).
        @else
            <strong>Rekap hasil hitung fisik yang sudah dikirim (Terkirim) saja.</strong>
            Dokumen yang masih Draft (belum dikirim, atau sedang direvisi) TIDAK dianggap hasil final -- hari itu tampil "Belum dihitung". Angka di sini APA ADANYA dari hasil hitung fisik, TIDAK ada perhitungan IN/OUT atau saldo berjalan.
        @endif
    </div>

    @php
        $recapRouteName = $isOpnameRecap ? 'operations.inventory-count.opname-recap' : 'operations.inventory-count.recap.index';
        $headerDays = $isOpnameRecap ? $dateRange : collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
        $tableWidth = 280 + $headerDays->count() * 110;
    @endphp

    @if($isOpnameRecap)
        {{-- Dashboard Opname: outlet/departemen (kalau boleh pilih bebas) +
             rentang tanggal bebas lewat date-range-picker, pengganti
             navigasi "Minggu Lalu/Depan" -- atas permintaan user supaya
             bisa lihat & filter lintas outlet/departemen/tanggal seperti
             dashboard utama aplikasi. --}}
        <x-sf.card>
            <form method="GET" action="{{ route($recapRouteName) }}" class="space-y-3">
                @if($canPickAnyScope)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <select name="outlet_id" class="sf-input min-h-11 text-sm" onchange="this.form.submit()">
                            <option value="">Pilih outlet...</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}" @selected((string) request('outlet_id') === (string) $o->id)>{{ $o->name }}</option>
                            @endforeach
                        </select>
                        <select name="department_id" class="sf-input min-h-11 text-sm" onchange="this.form.submit()">
                            <option value="">Semua Departemen</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="include_draft" value="1" onchange="this.form.submit()" @checked($includeDraft) class="rounded border-gray-300 text-primary-700">
                    Tampilkan juga yang masih Draft (belum final)
                </label>
                {{-- Date-range-picker diletakkan PALING BAWAH form ini (bukan di
                     tengah) -- kalendernya mengambang (position: absolute) dan
                     melebar ke bawah saat dibuka, jadi kalau ada elemen lain
                     SETELAHNYA (mis. checkbox Draft), kalender akan menimpanya
                     secara visual (numpuk) begitu dibuka. Menaruhnya paling
                     akhir menghindari itu sama sekali. --}}
                <x-sf.date-range-picker name="date" :from="$rangeStart->toDateString()" :to="$rangeEnd->toDateString()" />
            </form>
        </x-sf.card>
    @else
        <x-sf.card>
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route($recapRouteName, array_filter(['week' => $weekStart->copy()->subDays(7)->toDateString(), 'template_id' => $template->id, 'outlet_id' => $canPickAnyScope ? $outlet?->id : null, 'department_id' => $canPickAnyScope ? $department?->id : null, 'include_draft' => $includeDraft ? 1 : null])) }}"
                   class="sf-btn-secondary min-h-10 px-3 text-sm"><i class="ti ti-chevron-left" aria-hidden="true"></i> Minggu Lalu</a>
                <div class="text-center">
                    <p class="text-xs text-gray-500">Minggu</p>
                    <p class="font-semibold text-gray-900">{{ $weekStart->translatedFormat('d M') }} &ndash; {{ $weekStart->copy()->addDays(6)->translatedFormat('d M Y') }}</p>
                </div>
                <a href="{{ route($recapRouteName, array_filter(['week' => $weekStart->copy()->addDays(7)->toDateString(), 'template_id' => $template->id, 'outlet_id' => $canPickAnyScope ? $outlet?->id : null, 'department_id' => $canPickAnyScope ? $department?->id : null, 'include_draft' => $includeDraft ? 1 : null])) }}"
                   class="sf-btn-secondary min-h-10 px-3 text-sm">Minggu Depan <i class="ti ti-chevron-right" aria-hidden="true"></i></a>
            </div>
        </x-sf.card>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-{{ $isOpnameRecap ? '2' : '3' }} gap-2">
        <div class="relative sm:col-span-1">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
            <input type="search" x-model="$store.recap.search" placeholder="Cari nama item..." class="sf-input pl-9 min-h-11 text-sm">
        </div>
        <select x-model="$store.recap.categoryFilter" class="sf-input min-h-11 text-sm">
            <option value="">Semua Kategori</option>
            @foreach($distinctCategories as $category)
                <option value="{{ $category }}">{{ $category }}</option>
            @endforeach
        </select>
        @unless($isOpnameRecap)
        <a href="{{ route('operations.inventory-count.recap.export', array_filter(['template_id' => $template->id, 'week' => $weekStart->toDateString(), 'outlet_id' => $canPickAnyScope ? $outlet?->id : null, 'department_id' => $canPickAnyScope ? $department?->id : null])) }}"
           class="sf-btn-secondary min-h-11 flex items-center justify-center gap-1.5 text-sm">
            <i class="ti ti-download" aria-hidden="true"></i> Export
        </a>
        @endunless
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 -mx-4 lg:mx-0 max-h-[75vh] overflow-y-auto">
        {{-- Lebar EKSPLISIT = jumlah pasti lebar kolom (180+100+Nx110) --
             table-fixed + w-full/persentase membuat kolom disusutkan
             proporsional kalau viewport sempit, merusak offset sticky
             (pelajaran dari bug "tertindih" di Rekap Mingguan lama, lihat
             docs/technical/uat-inventory-simple-2026-10-13.md). N kolom
             hari sekarang bisa lebih dari 7 untuk Rekap Opname (rentang
             tanggal bebas), jadi lebarnya dihitung dinamis, bukan class
             Tailwind statis. --}}
        <table id="recap-table" class="text-xs border-collapse table-fixed" style="width: {{ $tableWidth }}px">
            <thead>
                <tr class="bg-gray-50">
                    <th rowspan="2" class="sticky top-0 left-0 z-30 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-600 w-[180px] align-bottom">Item</th>
                    <th rowspan="2" class="sticky top-0 left-[180px] z-30 bg-gray-50 px-2 py-2 text-left font-semibold text-gray-600 w-[100px] align-bottom">Lokasi</th>
                    @foreach($headerDays as $d)
                        <th class="sticky top-0 z-20 bg-gray-50 px-2 py-2 text-center font-semibold text-gray-600 border-l border-gray-200 w-[110px]">{{ $d->translatedFormat('D, d/m') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows->groupBy('subcategory') as $subcategory => $groupRows)
                    <tr class="bg-primary-50" x-show="$store.recap.categoryFilter === '' || $store.recap.categoryFilter === @js($subcategory)">
                        <td colspan="{{ 2 + $headerDays->count() }}" class="px-3 py-1.5 bg-primary-50">
                            <span class="sticky left-0 text-xs font-bold uppercase tracking-wide text-primary-800 inline-block">{{ $subcategory }}</span>
                        </td>
                    </tr>
                    @foreach($groupRows as $row)
                        @include('operations.inventory-count.partials.recap-row', ['row' => $row])
                    @endforeach
                @empty
                    <tr><td colspan="{{ 2 + $headerDays->count() }}" class="px-3 py-6 text-center text-gray-400">{{ $isOpnameRecap ? 'Belum ada item terdaftar untuk departemen ini.' : 'Belum ada item di template ini.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
