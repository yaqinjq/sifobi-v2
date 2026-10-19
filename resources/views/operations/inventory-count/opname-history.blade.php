@extends('layouts.app')

@section('title', 'Data Opname')

@section('content')
<x-sf.page-header title="Data Opname" subtitle="Read-only -- data Opname biasa yang sudah diisi staf, cuma ditampilkan di sini." back="{{ route('operations.inventory-count.index') }}">
    <x-slot:actions>
        <a href="{{ route('operations.inventory-count.opname-recap', $isSuperAdmin ? array_filter(['outlet_id' => request('outlet_id'), 'department_id' => request('department_id')]) : []) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">
            <i class="ti ti-table" aria-hidden="true"></i> Tampilan Tabel Mingguan
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    @if($isSuperAdmin)
        <x-sf.card title="Pilih Outlet & Departemen">
            <form method="GET" action="{{ route('operations.inventory-count.opname-history') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
            description="{{ $isSuperAdmin ? 'Pilih outlet & departemen di atas untuk melihat data Opname.' : 'Akun Anda belum ditetapkan ke outlet & departemen. Hubungi Super Admin.' }}"
        />
    @else
        <x-sf.card>
            <form method="GET" action="{{ route('operations.inventory-count.opname-history') }}" class="flex gap-3">
                @if($isSuperAdmin)
                    <input type="hidden" name="outlet_id" value="{{ request('outlet_id') }}">
                    <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                @endif
                <select name="status" class="sf-input min-h-11 w-auto" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
                    <option value="SUBMITTED" @selected(request('status') === 'SUBMITTED')>Terkirim</option>
                    <option value="APPROVED" @selected(request('status') === 'APPROVED')>Disetujui</option>
                    <option value="PROCESSED" @selected(request('status') === 'PROCESSED')>Diposting</option>
                </select>
            </form>
        </x-sf.card>

        <div class="space-y-3">
            @forelse($sessions as $session)
                <a href="{{ route('operations.opname.show', $session) }}" class="block">
                    <x-sf.card>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900">{{ $session->outlet?->name }} &middot; {{ $session->department?->name }}</p>
                                <p class="text-xs text-gray-500">{{ $session->business_date?->translatedFormat('d M Y') }} &middot; {{ $session->counted_items_count }}/{{ $session->items_count }} item terhitung</p>
                            </div>
                            <span class="{{ $session->status_badge_class }}">{{ $session->status }}</span>
                        </div>
                    </x-sf.card>
                </a>
            @empty
                <x-sf.empty-state icon="INV" title="Belum ada data Opname" description="Sesi Opname yang pernah dibuat untuk outlet & departemen ini akan muncul di sini." />
            @endforelse
        </div>

        <div class="flex items-center justify-center">
            {{ $sessions->links() }}
        </div>
    @endif
</div>
@endsection
