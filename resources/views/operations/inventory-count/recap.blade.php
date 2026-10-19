@extends('layouts.app')

@php $isOpnameRecap = $isOpnameRecap ?? false; @endphp

@section('title', 'Rekap Hasil Hitung Mingguan')

@section('content')
<x-sf.page-header title="Rekap Hasil Hitung Mingguan{{ $isOpnameRecap ? ' (Data Opname)' : '' }}" subtitle="{{ $isOpnameRecap ? 'Opname' : $template->name }} -- {{ $outlet?->name }} / {{ $department?->name }}" back="{{ $isOpnameRecap ? route('operations.inventory-count.opname-history') : route('operations.inventory-count.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4"
     x-data
     x-init="Alpine.store('recap', { search: '', categoryFilter: '' })">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <strong>Rekap hasil hitung fisik yang sudah dikirim (Terkirim) saja.</strong>
        Dokumen yang masih Draft (belum dikirim, atau sedang direvisi) TIDAK dianggap hasil final -- hari itu tampil "Belum dihitung". Angka di sini APA ADANYA dari hasil hitung fisik, TIDAK ada perhitungan IN/OUT atau saldo berjalan.
    </div>

    @php
        $prevWeekParams = array_filter(['week' => $weekStart->copy()->subDays(7)->toDateString(), 'outlet_id' => $isSuperAdmin ? $outlet?->id : null, 'department_id' => $isSuperAdmin ? $department?->id : null]);
        $nextWeekParams = array_filter(['week' => $weekStart->copy()->addDays(7)->toDateString(), 'outlet_id' => $isSuperAdmin ? $outlet?->id : null, 'department_id' => $isSuperAdmin ? $department?->id : null]);
        if (! $isOpnameRecap) {
            $prevWeekParams['template_id'] = $template->id;
            $nextWeekParams['template_id'] = $template->id;
        }
        $recapRouteName = $isOpnameRecap ? 'operations.inventory-count.opname-recap' : 'operations.inventory-count.recap.index';
    @endphp
    <x-sf.card>
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route($recapRouteName, $prevWeekParams) }}"
               class="sf-btn-secondary min-h-10 px-3 text-sm"><i class="ti ti-chevron-left" aria-hidden="true"></i> Minggu Lalu</a>
            <div class="text-center">
                <p class="text-xs text-gray-500">Minggu</p>
                <p class="font-semibold text-gray-900">{{ $weekStart->translatedFormat('d M') }} &ndash; {{ $weekStart->copy()->addDays(6)->translatedFormat('d M Y') }}</p>
            </div>
            <a href="{{ route($recapRouteName, $nextWeekParams) }}"
               class="sf-btn-secondary min-h-10 px-3 text-sm">Minggu Depan <i class="ti ti-chevron-right" aria-hidden="true"></i></a>
        </div>
    </x-sf.card>

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
        <a href="{{ route('operations.inventory-count.recap.export', array_filter(['template_id' => $template->id, 'week' => $weekStart->toDateString(), 'outlet_id' => $isSuperAdmin ? $outlet?->id : null, 'department_id' => $isSuperAdmin ? $department?->id : null])) }}"
           class="sf-btn-secondary min-h-11 flex items-center justify-center gap-1.5 text-sm">
            <i class="ti ti-download" aria-hidden="true"></i> Export
        </a>
        @endunless
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 -mx-4 lg:mx-0 max-h-[75vh] overflow-y-auto">
        {{-- Lebar EKSPLISIT = jumlah pasti lebar kolom (180+100+7x110=1050px)
             -- table-fixed + w-full/persentase membuat kolom disusutkan
             proporsional kalau viewport sempit, merusak offset sticky
             (pelajaran dari bug "tertindih" di Rekap Mingguan lama, lihat
             docs/technical/uat-inventory-simple-2026-10-13.md). --}}
        <table id="recap-table" class="w-[1050px] text-xs border-collapse table-fixed">
            <thead>
                <tr class="bg-gray-50">
                    <th rowspan="2" class="sticky top-0 left-0 z-30 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-600 w-[180px] align-bottom">Item</th>
                    <th rowspan="2" class="sticky top-0 left-[180px] z-30 bg-gray-50 px-2 py-2 text-left font-semibold text-gray-600 w-[100px] align-bottom">Lokasi</th>
                    @for($i = 0; $i < 7; $i++)
                        @php $d = $weekStart->copy()->addDays($i); @endphp
                        <th class="sticky top-0 z-20 bg-gray-50 px-2 py-2 text-center font-semibold text-gray-600 border-l border-gray-200 w-[110px]">{{ $d->translatedFormat('D, d/m') }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @forelse($rows->groupBy('subcategory') as $subcategory => $groupRows)
                    <tr class="bg-primary-50" x-show="$store.recap.categoryFilter === '' || $store.recap.categoryFilter === @js($subcategory)">
                        <td colspan="9" class="px-3 py-1.5 bg-primary-50">
                            <span class="sticky left-0 text-xs font-bold uppercase tracking-wide text-primary-800 inline-block">{{ $subcategory }}</span>
                        </td>
                    </tr>
                    @foreach($groupRows as $row)
                        @include('operations.inventory-count.partials.recap-row', ['row' => $row])
                    @endforeach
                @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-gray-400">{{ $isOpnameRecap ? 'Belum ada item terdaftar untuk departemen ini.' : 'Belum ada item di template ini.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
