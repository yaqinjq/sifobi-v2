@extends('layouts.app')

@section('title', 'Spoil & Waste')

@section('content')
<x-sf.page-header title="Spoil & Waste" subtitle="{{ $headerSubtitle ?? auth()->user()->outlet->name ?? 'Semua outlet' }}">
    <x-slot:actions>
        @if($canSwitchOutlet)
            <a href="{{ route('operations.spoil-wastes.select-outlet') }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Ganti Outlet</a>
        @endif
        @can('approve_spoil')
            <a href="{{ route('operations.spoil-wastes.import-form') }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Import</a>
        @endcan
        @can('record_spoil')
            <a href="{{ route('operations.spoil-wastes.fill') }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Isi Massal</a>
        @endcan
        @role('SUPER_ADMIN')
            <a href="{{ route('operations.spoil-wastes.import-bulanan.form') }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Import Bulanan</a>
        @endrole
        @can('record_spoil')
            <a href="{{ route('operations.spoil-wastes.create') }}" class="sf-btn-primary min-h-11 px-3">+ Catat</a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

@php
    $allIds = $spoilWastes->getCollection()->pluck('id')->values();
    $statusById = $spoilWastes->getCollection()->pluck('status', 'id');
@endphp

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4"
     x-data="{
         selected: [],
         statusById: @js($statusById),
         deleteOpen: false, deleteUrl: '', deleteName: '',
         get pendingSelected() { return this.selected.filter(id => this.statusById[id] === 'PENDING'); },
     }">
    @if($needsOutletSelection)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Akun Anda punya akses ke beberapa outlet. Pilih outlet aktif dulu untuk mencatat/melihat Spoil & Waste.
            <a href="{{ route('operations.spoil-wastes.select-outlet') }}" class="font-semibold underline">Pilih Outlet</a>
        </div>
    @endif

    @if($duplicateCount > 0)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <strong>{{ $duplicateCount }}</strong> laporan spoil pending memakai foto duplikat.
            <a href="{{ route('operations.spoil-wastes.index', ['filter' => 'duplicate', 'range' => request('range')]) }}" class="font-semibold underline">Lihat</a>
        </div>
    @endif

    <x-sf.card>
        <form method="GET" action="{{ route('operations.spoil-wastes.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @if($canFilterScope)
                <select name="outlet_id" class="sf-input text-base min-h-11">
                    <option value="">Semua outlet</option>
                    @foreach($filterOutlets as $filterOutlet)
                        <option value="{{ $filterOutlet->id }}" @selected((string) request('outlet_id') === (string) $filterOutlet->id)>{{ $filterOutlet->name }}</option>
                    @endforeach
                </select>
                <select name="department_id" class="sf-input text-base min-h-11">
                    <option value="">Semua departemen</option>
                    @foreach($filterDepartments as $filterDepartment)
                        <option value="{{ $filterDepartment->id }}" @selected((string) request('department_id') === (string) $filterDepartment->id)>{{ $filterDepartment->name }}</option>
                    @endforeach
                </select>
            @endif
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
                            <img src="{{ route('operations.spoil-wastes.photo', $spoil) }}" alt="Foto spoil" class="h-16 w-16 rounded-xl object-cover border border-gray-100">
                        @else
                            <div class="h-16 w-16 rounded-xl bg-gray-100"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ $spoil->subjectName() }} @if($spoil->isMenu())<span class="badge-menu-item">Menu</span>@endif</p>
                            @if($canFilterScope)
                                <p class="text-sm text-gray-500">Outlet: {{ $spoil->outlet?->name ?? '-' }}</p>
                            @endif
                            <p class="text-sm text-gray-500">Dept: {{ $spoil->department?->name ?? '-' }} | Qty: {{ $spoil->qty }} {{ $spoil->unit?->abbreviation }}</p>
                            <p class="text-sm text-gray-500">Alasan: {{ $spoil->reason_label }}</p>
                            <p class="text-xs text-gray-400 mt-1">Diinput oleh {{ $spoil->createdBy?->name ?? '-' }}@if($spoil->createdBy) ({{ $spoil->createdBy->primary_role }})@endif</p>
                            @if($spoil->is_duplicate_photo)
                                <p class="text-xs font-semibold text-red-700 mt-1">Foto duplikat</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        @can('approve_spoil')
                            <label class="flex items-center gap-2 text-xs text-gray-500">
                                <input type="checkbox" x-model="selected" value="{{ $spoil->id }}" class="rounded border-gray-300 text-primary-700">
                                Pilih
                            </label>
                        @endcan
                        <div class="flex items-center gap-2 ml-auto">
                            @can('approve_spoil')
                                <x-icon-btn
                                    icon="delete"
                                    label="Hapus"
                                    color="red"
                                    @click="deleteOpen = true; deleteUrl = {{ \Illuminate\Support\Js::from(route('operations.spoil-wastes.destroy', $spoil)) }}; deleteName = {{ \Illuminate\Support\Js::from($spoil->subjectName()) }}"
                                />
                            @endcan
                            <x-icon-btn
                                icon="view"
                                label="Detail"
                                color="gray"
                                href="{{ route('operations.spoil-wastes.show', $spoil) }}"
                            />
                        </div>
                    </div>
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
                            @can('approve_spoil')
                                <th class="px-4 py-3 w-10">
                                    @if($allIds->isNotEmpty())
                                        <input type="checkbox" class="rounded border-gray-300 text-primary-700"
                                               :checked="selected.length === {{ $allIds->count() }} && {{ $allIds->count() }} > 0"
                                               @change="selected = $event.target.checked ? @js($allIds) : []">
                                    @endif
                                </th>
                            @endcan
                            <th class="px-4 py-3">No</th>
                            <x-sf.sortable-th column="recorded_at" :sort="$sort" :direction="$direction">Waktu</x-sf.sortable-th>
                            <th class="px-4 py-3">Item</th>
                            @if($canFilterScope)
                                <th class="px-4 py-3">Outlet</th>
                            @endif
                            <th class="px-4 py-3">Dept</th>
                            <x-sf.sortable-th column="qty_in_base_unit" :sort="$sort" :direction="$direction" align="right">Qty</x-sf.sortable-th>
                            <th class="px-4 py-3">Alasan</th>
                            <th class="px-4 py-3">Diinput Oleh</th>
                            <th class="px-4 py-3">Foto</th>
                            <x-sf.sortable-th column="status" :sort="$sort" :direction="$direction">Status</x-sf.sortable-th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($spoilWastes as $spoil)
                            <tr>
                                @can('approve_spoil')
                                    <td class="px-4 py-3">
                                        <input type="checkbox" x-model="selected" value="{{ $spoil->id }}" class="rounded border-gray-300 text-primary-700">
                                    </td>
                                @endcan
                                <td class="px-4 py-3 text-gray-500">{{ $spoilWastes->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ optional($spoil->recorded_at)->format('d M H:i') }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $spoil->subjectName() }} @if($spoil->isMenu())<span class="badge-menu-item">Menu</span>@endif</td>
                                @if($canFilterScope)
                                    <td class="px-4 py-3">{{ $spoil->outlet?->name ?? '-' }}</td>
                                @endif
                                <td class="px-4 py-3">{{ $spoil->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $spoil->qty }} {{ $spoil->unit?->abbreviation }}</td>
                                <td class="px-4 py-3">{{ $spoil->reason_label }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $spoil->createdBy?->name ?? '-' }}
                                    @if($spoil->createdBy)
                                        <span class="block text-xs text-gray-400">{{ $spoil->createdBy->primary_role }}</span>
                                    @endif
                                </td>
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
                                    <div class="flex items-center justify-end gap-1">
                                        @can('approve_spoil')
                                            <x-icon-btn
                                                icon="delete"
                                                label="Hapus"
                                                color="red"
                                                size="sm"
                                                @click="deleteOpen = true; deleteUrl = {{ \Illuminate\Support\Js::from(route('operations.spoil-wastes.destroy', $spoil)) }}; deleteName = {{ \Illuminate\Support\Js::from($spoil->subjectName()) }}"
                                            />
                                        @endcan
                                        <x-icon-btn
                                            icon="view"
                                            label="Detail"
                                            color="gray"
                                            size="sm"
                                            href="{{ route('operations.spoil-wastes.show', $spoil) }}"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canFilterScope ? 12 : 11 }}" class="px-4 py-10 text-center text-gray-500">Belum ada spoil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    <div class="flex items-center justify-between gap-2 flex-wrap">
        <x-sf.per-page-selector :options="$perPageOptions" :current="$perPage" />
        {{ $spoilWastes->links() }}
    </div>

    @can('approve_spoil')
        <div x-show="selected.length > 0" x-cloak
             class="fixed inset-x-0 bottom-16 z-30 border-t border-gray-200 bg-white px-4 py-3 shadow-lg md:bottom-0 md:px-6">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600"><span class="font-semibold text-gray-900" x-text="selected.length"></span> terpilih</p>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('operations.spoil-wastes.bulk-approve') }}"
                          x-show="pendingSelected.length > 0"
                          @submit="if (! confirm('Approve ' + pendingSelected.length + ' spoil/waste pending terpilih?')) { $event.preventDefault(); }">
                        @csrf
                        <template x-for="id in pendingSelected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                        <button type="submit" class="sf-btn-secondary min-h-10 px-3 text-sm"><i class="ti ti-checks text-base" aria-hidden="true"></i> Approve (<span x-text="pendingSelected.length"></span>)</button>
                    </form>
                    <form method="POST" action="{{ route('operations.spoil-wastes.bulk-delete') }}"
                          @submit="if (! confirm('Hapus permanen ' + selected.length + ' record spoil terpilih? Yang sudah Approved akan dikembalikan dulu stoknya.')) { $event.preventDefault(); }">
                        @csrf
                        <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                        <button type="submit" class="sf-btn-danger min-h-10 px-3 text-sm"><i class="ti ti-trash text-base" aria-hidden="true"></i> Hapus (<span x-text="selected.length"></span>)</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div x-show="deleteOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-gray-900/40 p-4">
        <div class="sf-card w-full max-w-sm p-4" @click.outside="deleteOpen = false">
            <h2 class="font-heading font-semibold text-gray-900">Hapus record spoil?</h2>
            <p class="text-sm text-gray-500 mt-2">
                Record spoil untuk <span class="font-semibold text-gray-800" x-text="deleteName"></span> akan dihapus
                permanen. Kalau sudah Approved, stok yang sempat terpotong akan dikembalikan dulu.
            </p>
            <div class="mt-4 flex gap-2">
                <button type="button" class="sf-btn-secondary flex-1" @click="deleteOpen = false">Batal</button>
                <form method="POST" :action="deleteUrl" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sf-btn-danger w-full">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
