@extends('layouts.app')

@section('title', 'Riwayat Inventory Simple')

@section('content')
<x-sf.page-header title="Riwayat Inventory Simple" subtitle="Pendataan fisik -- belum mengubah saldo stok sistem." back="{{ route('operations.inventory-count.index') }}">
    <x-slot:actions>
        <a href="{{ route('operations.inventory-count.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">
            <i class="ti ti-file-spreadsheet" aria-hidden="true"></i> Export
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    @if($isSuperAdmin)
        <x-sf.card title="Pilih Outlet & Departemen">
            <form method="GET" action="{{ route('operations.inventory-count.history') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
            description="{{ $isSuperAdmin ? 'Pilih outlet & departemen di atas untuk melihat riwayat.' : 'Akun Anda belum ditetapkan ke outlet & departemen. Hubungi Super Admin.' }}"
        />
    @else
    <x-sf.card>
        <form method="GET" action="{{ route('operations.inventory-count.history') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @if($isSuperAdmin)
                <input type="hidden" name="outlet_id" value="{{ request('outlet_id') }}">
                <input type="hidden" name="department_id" value="{{ request('department_id') }}">
            @endif
            <select name="status" class="sf-input min-h-11" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
                <option value="SUBMITTED" @selected(request('status') === 'SUBMITTED')>Terkirim</option>
            </select>
            <select name="template_id" class="sf-input min-h-11" onchange="this.form.submit()">
                <option value="">Semua template</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) request('template_id') === (string) $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
        </form>
    </x-sf.card>

    <div class="space-y-3">
        @forelse($documents as $doc)
            <x-sf.card>
                <a href="{{ route('operations.inventory-count.show', $doc) }}" class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 truncate">{{ $doc->template?->name }}</p>
                        <p class="text-xs text-gray-500">{{ $doc->outlet?->name }} &middot; {{ $doc->department?->name }} &middot; {{ $doc->business_date->translatedFormat('d M Y') }}</p>
                    </div>
                    <span class="{{ $doc->status_badge_class }}">{{ $doc->status_label }}</span>
                </a>
            </x-sf.card>
        @empty
            <x-sf.empty-state icon="INV" title="Belum ada riwayat" description="Hasil hitung yang pernah dibuat akan muncul di sini." />
        @endforelse
    </div>

    <div class="flex items-center justify-center">
        {{ $documents->links() }}
    </div>
    @endif
</div>
@endsection
