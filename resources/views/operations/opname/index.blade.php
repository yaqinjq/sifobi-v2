@extends('layouts.app')

@section('title', 'Daily Opname')

@section('content')
<x-sf.page-header title="Daily Opname" subtitle="Hitung fisik stok harian">
    <x-slot:actions>
        @can('input_opname')
            <a href="{{ route('operations.opname.create-historical') }}" class="sf-btn-secondary min-h-11 px-3 text-sm">Input Historis</a>
            <a href="{{ route('operations.opname.create') }}" class="sf-btn-primary min-h-11 px-3">
                <i class="ti ti-plus text-base" aria-hidden="true"></i>
                Mulai Opname
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

@php
    $draftIds = $sessions->where('status', \App\Modules\Operations\Models\OpnameSession::STATUS_DRAFT)->pluck('id')->values();
    $submittedIds = $sessions->where('status', \App\Modules\Operations\Models\OpnameSession::STATUS_SUBMITTED)->pluck('id')->values();
@endphp

<div x-data="{ selectedDraft: [], selectedSubmitted: [] }" class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('operations.opname.index') }}" class="flex flex-wrap gap-2">
            @if($canFilterOutlet)
                <select name="brand_id" onchange="this.form.submit()" class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">
                    <option value="">Semua Brand</option>
                    @foreach($filterBrands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) request('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>

                <select name="outlet_id" onchange="this.form.submit()" class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">
                    <option value="">Semua Outlet</option>
                    @foreach($filterOutlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            <select name="department_id" onchange="this.form.submit()" class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">
                <option value="">Semua Departemen</option>
                @foreach($filterDepartments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>

            <select name="status" class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
                <option value="SUBMITTED" @selected(request('status') === 'SUBMITTED')>Submitted</option>
                <option value="PROCESSED" @selected(request('status') === 'PROCESSED')>Processed</option>
            </select>
            <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()" class="sf-input py-2 text-sm flex-shrink-0 w-auto min-h-11">

            @if(request()->hasAny(['brand_id', 'outlet_id', 'department_id', 'status', 'date']))
                <a href="{{ route('operations.opname.index') }}" class="sf-btn-secondary py-2 text-sm min-h-11">Reset</a>
            @endif
        </form>
    </x-sf.card>

    <div class="lg:hidden space-y-3">
        @forelse($sessions as $session)
            <x-sf.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2">
                        @if($session->status === \App\Modules\Operations\Models\OpnameSession::STATUS_DRAFT)
                            @can('input_opname')
                                <input type="checkbox" x-model="selectedDraft" value="{{ $session->id }}" class="mt-1 rounded border-gray-300 text-primary-700 shrink-0">
                            @endcan
                        @elseif($session->status === \App\Modules\Operations\Models\OpnameSession::STATUS_SUBMITTED)
                            @can('approve_opname')
                                <input type="checkbox" x-model="selectedSubmitted" value="{{ $session->id }}" class="mt-1 rounded border-gray-300 text-primary-700 shrink-0">
                            @endcan
                        @endif
                        <div>
                            <span class="{{ $session->status_badge_class }}">{{ $session->status }}</span>
                            <p class="font-semibold text-gray-900 mt-2">{{ $session->outlet?->name ?? '-' }}</p>
                            <p class="text-sm text-gray-500">{{ $session->department?->name ?? 'Semua Departemen' }}</p>
                            <p class="text-sm text-gray-500">{{ optional($session->opname_date)->format('d M Y') }} | {{ $session->shift ?: '-' }}</p>
                            <p class="text-sm text-gray-500">{{ $session->items_count }} item</p>
                        </div>
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
                            <th class="px-4 py-3 w-10">
                                @if($draftIds->isNotEmpty())
                                    @can('input_opname')
                                        <input type="checkbox" title="Pilih semua Draft" class="rounded border-gray-300 text-primary-700"
                                               :checked="selectedDraft.length === {{ $draftIds->count() }} && {{ $draftIds->count() }} > 0"
                                               @change="selectedDraft = $event.target.checked ? @js($draftIds) : []">
                                    @endcan
                                @endif
                            </th>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Outlet</th>
                            <th class="px-4 py-3">Departemen</th>
                            <th class="px-4 py-3">Shift</th>
                            <th class="px-4 py-3 text-right">Items</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($sessions as $session)
                            <tr>
                                <td class="px-4 py-3">
                                    @if($session->status === \App\Modules\Operations\Models\OpnameSession::STATUS_DRAFT)
                                        @can('input_opname')
                                            <input type="checkbox" x-model="selectedDraft" value="{{ $session->id }}" class="rounded border-gray-300 text-primary-700">
                                        @endcan
                                    @elseif($session->status === \App\Modules\Operations\Models\OpnameSession::STATUS_SUBMITTED)
                                        @can('approve_opname')
                                            <input type="checkbox" x-model="selectedSubmitted" value="{{ $session->id }}" class="rounded border-gray-300 text-primary-700">
                                        @endcan
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $sessions->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">{{ optional($session->opname_date)->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $session->outlet?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $session->department?->name ?? 'Semua Departemen' }}</td>
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
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500">Belum ada sesi opname.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    <div class="flex items-center justify-between gap-2 flex-wrap">
        <x-sf.per-page-selector :options="$perPageOptions" :current="$perPage" />
        {{ $sessions->links() }}
    </div>

    @can('input_opname')
        <div x-show="selectedDraft.length > 0" x-cloak
             class="fixed inset-x-0 bottom-16 z-30 border-t border-gray-200 bg-white px-4 py-3 shadow-lg md:bottom-0 md:px-6">
            <form method="POST" action="{{ route('operations.opname.bulk-submit') }}"
                  class="mx-auto flex max-w-7xl items-center justify-between gap-3"
                  @submit="if (! confirm('Submit ' + selectedDraft.length + ' sesi opname draft terpilih untuk approval?')) { $event.preventDefault(); }">
                @csrf
                <template x-for="id in selectedDraft" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-900" x-text="selectedDraft.length"></span> draft terpilih
                </p>
                <button type="submit" class="sf-btn-primary min-h-11 px-4">
                    <i class="ti ti-send text-base" aria-hidden="true"></i>
                    Submit Terpilih
                </button>
            </form>
        </div>
    @endcan

    @can('approve_opname')
        <div x-show="selectedSubmitted.length > 0" x-cloak
             class="fixed inset-x-0 bottom-16 z-30 border-t border-gray-200 bg-white px-4 py-3 shadow-lg md:bottom-0 md:px-6">
            <form method="POST" action="{{ route('operations.opname.bulk-approve') }}"
                  class="mx-auto flex max-w-7xl items-center justify-between gap-3"
                  @submit="if (! confirm('Approve ' + selectedSubmitted.length + ' sesi opname submitted terpilih ke stock ledger?')) { $event.preventDefault(); }">
                @csrf
                <template x-for="id in selectedSubmitted" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-900" x-text="selectedSubmitted.length"></span> submitted terpilih
                </p>
                <button type="submit" class="sf-btn-primary min-h-11 px-4">
                    <i class="ti ti-checks text-base" aria-hidden="true"></i>
                    Approve Terpilih
                </button>
            </form>
        </div>
    @endcan
</div>
@endsection
