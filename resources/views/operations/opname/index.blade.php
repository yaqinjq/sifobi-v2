@extends('layouts.app')

@section('title', 'Daily Opname')

@section('content')
<x-sf.page-header title="Daily Opname" subtitle="Hitung fisik stok harian">
    <x-slot:actions>
        @can('input_opname')
            <a href="{{ route('operations.opname.create') }}" class="sf-btn-primary min-h-11 px-3">+ Mulai</a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('operations.opname.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <select name="status" class="sf-input text-base min-h-11">
                <option value="">Semua status</option>
                <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
                <option value="SUBMITTED" @selected(request('status') === 'SUBMITTED')>Submitted</option>
                <option value="PROCESSED" @selected(request('status') === 'PROCESSED')>Processed</option>
            </select>
            <input type="date" name="date" value="{{ request('date') }}" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="lg:hidden space-y-3">
        @forelse($sessions as $session)
            <x-sf.card>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="{{ $session->status_badge_class }}">{{ $session->status }}</span>
                        <p class="font-semibold text-gray-900 mt-2">{{ $session->outlet?->name ?? '-' }}</p>
                        <p class="text-sm text-gray-500">{{ optional($session->opname_date)->format('d M Y') }} | {{ $session->shift ?: '-' }}</p>
                        <p class="text-sm text-gray-500">{{ $session->items_count }} item</p>
                    </div>
                    <x-icon-btn
                        icon="view"
                        label="Detail"
                        color="gray"
                        href="{{ route('operations.opname.show', $session) }}"
                    />
                </div>
            </x-sf.card>
        @empty
            <x-sf.card>
                <div class="text-center py-8">
                    <p class="font-semibold text-gray-900">Belum ada sesi opname.</p>
                    <p class="text-sm text-gray-500 mt-1">Mulai sesi untuk menghitung stok fisik.</p>
                </div>
            </x-sf.card>
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Outlet</th>
                            <th class="px-4 py-3">Shift</th>
                            <th class="px-4 py-3 text-right">Items</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($sessions as $session)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $sessions->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">{{ optional($session->opname_date)->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $session->outlet?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $session->shift ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $session->items_count }}</td>
                                <td class="px-4 py-3"><span class="{{ $session->status_badge_class }}">{{ $session->status }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <x-icon-btn
                                        icon="view"
                                        label="Detail"
                                        color="gray"
                                        size="sm"
                                        href="{{ route('operations.opname.show', $session) }}"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada sesi opname.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    {{ $sessions->links() }}
</div>
@endsection
