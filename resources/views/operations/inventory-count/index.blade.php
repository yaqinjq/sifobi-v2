@extends('layouts.app')

@section('title', 'Inventory Simple')

@section('content')
<x-sf.page-header title="Inventory Simple" subtitle="Pendataan fisik -- belum mengubah saldo stok sistem." />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        Form ini untuk mencatat hasil hitung fisik saja. <strong>Belum mengubah saldo stok sistem.</strong>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if($isSuperAdmin)
        <x-sf.card title="Pilih Outlet & Departemen">
            <form method="GET" action="{{ route('operations.inventory-count.index') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <select name="outlet_id" class="sf-input min-h-11" onchange="this.form.submit()">
                    <option value="">Pilih outlet...</option>
                    @foreach($outlets as $o)
                        <option value="{{ $o->id }}" @selected((string) request('outlet_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
                <select name="department_id" class="sf-input min-h-11" onchange="this.form.submit()">
                    <option value="">Pilih departemen...</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </form>
        </x-sf.card>
    @endif

    @if(! $hasValidScope)
        <x-sf.empty-state
            icon="INV"
            title="Belum ada scope outlet & departemen yang sah"
            description="Akun Anda belum ditetapkan ke outlet & departemen. Hubungi Super Admin untuk mengatur ini sebelum bisa mengisi Inventory Simple."
        />
    @else
        <x-sf.card>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">Outlet</p>
                    <p class="font-semibold text-gray-900">{{ $outlet?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Departemen</p>
                    <p class="font-semibold text-gray-900">{{ $department?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Tanggal</p>
                    <p class="font-semibold text-gray-900">{{ now()->translatedFormat('d M Y') }}</p>
                </div>
            </div>
        </x-sf.card>

        <x-sf.card title="Isi Inventory">
            @if($templates->isEmpty())
                <p class="text-sm text-gray-500">Belum ada template Inventory Simple untuk departemen ini. Hubungi Super Admin.</p>
            @else
                <div class="space-y-2">
                    @foreach($templates as $template)
                        <div class="rounded-xl border border-gray-100 p-3">
                            <form method="POST" action="{{ route('operations.inventory-count.start') }}" class="flex items-center justify-between gap-3">
                                @csrf
                                <input type="hidden" name="template_id" value="{{ $template->id }}">
                                @if($isSuperAdmin)
                                    <input type="hidden" name="outlet_id" value="{{ request('outlet_id') }}">
                                    <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $template->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $template->frequency }} @if(! $template->is_configured) &middot; <span class="text-amber-600">belum terverifikasi</span> @endif</p>
                                </div>
                                <button type="submit" class="sf-btn-primary min-h-11 px-4 shrink-0">Mulai</button>
                            </form>
                            <a href="{{ route('operations.inventory-count.recap.index', array_filter(['template_id' => $template->id, 'outlet_id' => $isSuperAdmin ? request('outlet_id') : null, 'department_id' => $isSuperAdmin ? request('department_id') : null])) }}"
                               class="mt-2 sf-btn-secondary min-h-10 w-full flex items-center justify-center gap-1.5 text-sm">
                                <i class="ti ti-table" aria-hidden="true"></i> Lihat Rekap Hasil Hitung Mingguan
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-sf.card>

        <x-sf.card title="Riwayat Terbaru">
            @forelse($recentDocuments as $doc)
                <a href="{{ route('operations.inventory-count.show', $doc) }}" class="flex items-center justify-between gap-3 py-2 border-b border-gray-50 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 truncate">{{ $doc->template?->name }}</p>
                        <p class="text-xs text-gray-500">{{ $doc->business_date->translatedFormat('d M Y') }}</p>
                    </div>
                    <span class="{{ $doc->status_badge_class }}">{{ $doc->status_label }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-500">Belum ada riwayat.</p>
            @endforelse
            <a href="{{ route('operations.inventory-count.history', $isSuperAdmin ? array_filter(['outlet_id' => request('outlet_id'), 'department_id' => request('department_id')]) : []) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700">
                Lihat semua riwayat <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="{{ route('operations.inventory-count.opname-history', $isSuperAdmin ? array_filter(['outlet_id' => request('outlet_id'), 'department_id' => request('department_id')]) : []) }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600">
                Lihat data Opname (yang sudah diisi) <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </x-sf.card>
    @endif
</div>
@endsection
