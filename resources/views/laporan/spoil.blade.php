@extends('layouts.app')

@section('title', 'Laporan Spoil & Waste')

@section('content')
<x-sf.page-header title="Laporan Spoil & Waste" subtitle="Rekap bahan rusak, tumpah, dan kadaluarsa">
    <x-slot:actions>
        <a href="{{ route('laporan.spoil.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.spoil') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <select name="department_id" class="sf-input text-base min-h-11">
                <option value="">Semua departemen</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? now()->startOfMonth()->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? now()->toDateString() }}" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Total Spoil" :value="number_format((int) ($summary->total_rows ?? 0))" />
        <x-sf.stat label="Total Qty Base" :value="number_format((float) ($summary->total_qty_base ?? 0), 4, ',', '.')" />
        <x-sf.stat label="Foto Duplikat" :value="number_format((int) ($summary->duplicate_photos ?? 0))" />
    </div>

    @if((int) ($summary->duplicate_photos ?? 0) > 0)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            Ada {{ (int) $summary->duplicate_photos }} laporan dengan foto duplikat pada filter ini.
        </div>
    @endif

    <div class="lg:hidden space-y-3">
        @forelse($spoils as $spoil)
            @php
                $statusClass = [
                    'PENDING' => 'badge-pending',
                    'APPROVED' => 'badge-approved',
                    'REJECTED' => 'badge-rejected',
                ][$spoil->status] ?? 'badge-draft';
            @endphp
            <x-sf.card>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="{{ $statusClass }}">{{ $spoil->status }}</span>
                        <span class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($spoil->recorded_at)->format('d M H:i') }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $spoil->item_name }}</p>
                        <p class="text-xs text-gray-500">{{ $spoil->outlet_name }} | {{ $spoil->department_name ?? '-' }}</p>
                    </div>
                    <p class="text-sm text-gray-700">Qty: {{ number_format((float) $spoil->qty, 4, ',', '.') }} {{ $spoil->unit }}</p>
                    <p class="text-sm text-gray-500">Alasan: {{ $spoil->reason_category }}</p>
                    @if($spoil->is_duplicate_photo)
                        <span class="badge-rejected">Foto duplikat</span>
                    @endif
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Belum ada spoil" description="Data spoil akan tampil sesuai filter periode." />
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Outlet</th>
                            <th class="px-4 py-3">Dept</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3">Alasan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Foto</th>
                            <th class="px-4 py-3">Duplikat?</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($spoils as $spoil)
                            @php
                                $statusClass = [
                                    'PENDING' => 'badge-pending',
                                    'APPROVED' => 'badge-approved',
                                    'REJECTED' => 'badge-rejected',
                                ][$spoil->status] ?? 'badge-draft';
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Carbon::parse($spoil->recorded_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $spoil->item_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $spoil->canonical_sku }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $spoil->outlet_name }}</td>
                                <td class="px-4 py-3">{{ $spoil->department_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $spoil->qty, 4, ',', '.') }} {{ $spoil->unit }}</td>
                                <td class="px-4 py-3">{{ $spoil->reason_category }}</td>
                                <td class="px-4 py-3"><span class="{{ $statusClass }}">{{ $spoil->status }}</span></td>
                                <td class="px-4 py-3">{{ $spoil->photo || $spoil->photo_path ? 'Ada' : '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($spoil->is_duplicate_photo)
                                        <span class="badge-rejected">Ya</span>
                                    @else
                                        <span class="badge-draft">Tidak</span>
                                    @endif
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

    {{ $spoils->links() }}
</div>
@endsection
