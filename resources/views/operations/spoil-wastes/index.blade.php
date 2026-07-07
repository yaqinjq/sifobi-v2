@extends('layouts.app')

@section('title', 'Spoil & Waste')

@section('content')
<x-sf.page-header title="Spoil & Waste" subtitle="{{ auth()->user()->outlet->name ?? 'Semua outlet' }}">
    <x-slot:actions>
        @can('record_spoil')
            <a href="{{ route('operations.spoil-wastes.create') }}" class="sf-btn-primary min-h-11 px-3">+ Catat</a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    @if($duplicateCount > 0)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <strong>{{ $duplicateCount }}</strong> laporan spoil pending memakai foto duplikat.
            <a href="{{ route('operations.spoil-wastes.index', ['filter' => 'duplicate', 'range' => request('range')]) }}" class="font-semibold underline">Lihat</a>
        </div>
    @endif

    <x-sf.card>
        <form method="GET" action="{{ route('operations.spoil-wastes.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <select name="status" class="sf-input text-base min-h-11">
                <option value="">Semua status</option>
                <option value="PENDING" @selected(request('status') === 'PENDING')>Pending</option>
                <option value="APPROVED" @selected(request('status') === 'APPROVED')>Approved</option>
                <option value="REJECTED" @selected(request('status') === 'REJECTED')>Rejected</option>
            </select>
            <select name="range" class="sf-input text-base min-h-11">
                <option value="today" @selected(request('range', 'today') === 'today')>Hari ini</option>
                <option value="7" @selected(request('range') === '7')>7 hari</option>
                <option value="30" @selected(request('range') === '30')>30 hari</option>
                <option value="all" @selected(request('range') === 'all')>Semua</option>
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari item" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="lg:hidden space-y-3">
        @forelse($spoilWastes as $spoil)
            <x-sf.card>
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <span class="{{ $spoil->status_badge_class }}">{{ $spoil->status }}</span>
                        <span class="text-xs text-gray-500">{{ optional($spoil->recorded_at)->format('d M H:i') }}</span>
                    </div>
                    <div class="flex gap-3">
                        @if($spoil->photo || $spoil->photo_path)
                            <img src="{{ asset('storage/'.($spoil->photo ?: $spoil->photo_path)) }}" alt="Foto spoil" class="h-16 w-16 rounded-xl object-cover border border-gray-100">
                        @else
                            <div class="h-16 w-16 rounded-xl bg-gray-100"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ $spoil->item?->name ?? '-' }}</p>
                            <p class="text-sm text-gray-500">Dept: {{ $spoil->department?->name ?? '-' }} | Qty: {{ $spoil->qty }} {{ $spoil->unit?->abbreviation }}</p>
                            <p class="text-sm text-gray-500">Alasan: {{ $spoil->reason_label }}</p>
                            @if($spoil->is_duplicate_photo)
                                <p class="text-xs font-semibold text-red-700 mt-1">Foto duplikat</p>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('operations.spoil-wastes.show', $spoil) }}" class="sf-btn-secondary min-h-11 w-full">Detail</a>
                </div>
            </x-sf.card>
        @empty
            <x-sf.card>
                <div class="text-center py-8">
                    <p class="font-semibold text-gray-900">Belum ada spoil.</p>
                    <p class="text-sm text-gray-500 mt-1">Catat bahan terbuang dari tombol di atas.</p>
                </div>
            </x-sf.card>
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Dept</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3">Alasan</th>
                            <th class="px-4 py-3">Foto</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($spoilWastes as $spoil)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $spoilWastes->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ optional($spoil->recorded_at)->format('d M H:i') }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $spoil->item?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $spoil->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $spoil->qty }} {{ $spoil->unit?->abbreviation }}</td>
                                <td class="px-4 py-3">{{ $spoil->reason_label }}</td>
                                <td class="px-4 py-3">
                                    @if($spoil->is_duplicate_photo)
                                        <span class="badge-rejected">Duplikat</span>
                                    @elseif($spoil->photo || $spoil->photo_path)
                                        <span class="badge-active">Ada</span>
                                    @else
                                        <span class="badge-draft">Tidak</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span class="{{ $spoil->status_badge_class }}">{{ $spoil->status }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('operations.spoil-wastes.show', $spoil) }}" class="sf-btn-secondary min-h-9 px-3">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500">Belum ada spoil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    {{ $spoilWastes->links() }}
</div>
@endsection
