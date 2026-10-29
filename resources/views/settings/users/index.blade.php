@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<div x-data="{
        selected: [],
        chosenAction: 'deactivate',
        outletId: '',
        actionRoutes: {
            activate: @js(route('settings.users.bulk-toggle-status')),
            deactivate: @js(route('settings.users.bulk-toggle-status')),
            change_outlet: @js(route('settings.users.bulk-change-outlet')),
            delete: @js(route('settings.users.bulk-delete')),
        },
        confirmMessages: {
            activate: 'Aktifkan __COUNT__ user terpilih?',
            deactivate: 'Nonaktifkan __COUNT__ user terpilih?',
            change_outlet: 'Pindahkan outlet __COUNT__ user terpilih?',
            delete: 'HAPUS __COUNT__ user terpilih? Tindakan ini tidak bisa dibatalkan.',
        },
     }">
<x-sf.page-header title="Manajemen User" subtitle="Kelola akun pengguna sistem">
    <x-slot:actions>
        <a href="{{ route('settings.users.import.form') }}" class="sf-btn-secondary text-sm min-h-11">
            Import User
        </a>
        <a href="{{ route('settings.users.create') }}" class="sf-btn-primary text-sm min-h-11">
            Tambah User
        </a>
    </x-slot:actions>
</x-sf.page-header>

<div class="sticky top-[4.25rem] z-20 bg-white border-b border-gray-100 px-4 py-3 lg:top-0">
    <form method="GET" action="{{ route('settings.users.index') }}" class="flex flex-col md:flex-row gap-2 max-w-6xl mx-auto">
        <input type="text"
               name="q"
               value="{{ $search }}"
               placeholder="Cari nama atau email..."
               class="sf-input text-base md:flex-1"
               autocomplete="off">

        <select name="role" class="sf-input text-base md:w-56">
            <option value="">Semua Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" @selected($roleFilter === $role->name)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>

        <select name="status" class="sf-input text-base md:w-44">
            <option value="">Semua Status</option>
            <option value="active" @selected($statusFilter === 'active')>Aktif</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Non-Aktif</option>
        </select>

        <select name="outlet_id" class="sf-input text-base md:w-56">
            <option value="">Semua Outlet</option>
            @foreach($outlets as $outlet)
                <option value="{{ $outlet->id }}" @selected((string) $outletFilter === (string) $outlet->id)>
                    {{ $outlet->name }}
                </option>
            @endforeach
        </select>

        <div class="grid grid-cols-2 gap-2 md:flex">
            <button type="submit" class="sf-btn-secondary min-h-11">Filter</button>
            <a href="{{ route('settings.users.index') }}" class="sf-btn-secondary min-h-11 text-center">Reset</a>
        </div>
    </form>
</div>

<div class="md:hidden p-4 space-y-3 pb-24">
    @forelse($users as $user)
        @php $isActive = strtoupper((string) $user->status) === 'ACTIVE'; @endphp
        <article class="sf-card p-4">
            <div class="flex items-center gap-3">
                @if($user->id !== auth()->id())
                    <input type="checkbox" x-model="selected" value="{{ $user->id }}" class="rounded border-gray-300 text-primary-700 shrink-0">
                @endif
                <img src="{{ $user->avatar_url }}"
                     class="w-11 h-11 rounded-full object-cover shrink-0"
                     alt="{{ $user->name }}">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                </div>
                @if($isActive)
                    <span class="badge-active">Aktif</span>
                @else
                    <span class="badge-draft">Non-Aktif</span>
                @endif
            </div>

            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="badge-active">{{ $user->primary_role }}</span>
                @if($user->outlet)
                    <span class="badge-draft">
                        {{ $user->outlet->name }}
                        @if($user->outlets->count() > 1)
                            +{{ $user->outlets->count() - 1 }} lainnya
                        @endif
                    </span>
                @endif
            </div>

            <div class="mt-3 flex items-start justify-end gap-2">
                <x-icon-btn
                    icon="edit"
                    label="Edit"
                    color="blue"
                    href="{{ route('settings.users.edit', $user) }}"
                />

                @if($user->id !== auth()->id())
                    <x-icon-btn
                        icon="toggle"
                        :label="$isActive ? 'Non-Aktifkan' : 'Aktifkan'"
                        :color="$isActive ? 'red' : 'green'"
                        href="{{ route('settings.users.toggle-status', $user) }}"
                        method="PATCH"
                    />
                @else
                    <x-icon-btn icon="toggle" label="Akun Anda" color="gray" disabled />
                @endif

                <x-icon-btn
                    icon="config"
                    label="Reset Password"
                    color="amber"
                    href="{{ route('settings.users.reset-password', $user) }}"
                    method="POST"
                    confirm="Reset password {{ $user->name }}?"
                />
            </div>
        </article>
    @empty
        <x-sf.empty-state title="Belum ada user"
                          description="Tambahkan user pertama untuk mulai mengelola akses sistem."
                          action="{{ route('settings.users.create') }}"
                          actionLabel="Tambah User" />
    @endforelse

    <div class="flex items-center justify-between gap-2 flex-wrap">
        <x-sf.per-page-selector :options="$perPageOptions" :current="$perPage" />
        {{ $users->links() }}
    </div>
</div>

<div class="hidden md:block p-6 pb-8">
    @php $selectableIds = $users->where('id', '!=', auth()->id())->pluck('id')->values(); @endphp
    <div class="sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            @if($selectableIds->isNotEmpty())
                                <input type="checkbox" title="Pilih semua di halaman ini" class="rounded border-gray-300 text-primary-700"
                                       :checked="selected.length === {{ $selectableIds->count() }} && {{ $selectableIds->count() }} > 0"
                                       @change="selected = $event.target.checked ? @js($selectableIds) : []">
                            @endif
                        </th>
                        <x-sf.sortable-th column="name" :sort="$sort" :direction="$direction">User</x-sf.sortable-th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                        <x-sf.sortable-th column="status" :sort="$sort" :direction="$direction">Status</x-sf.sortable-th>
                        <x-sf.sortable-th column="last_login_at" :sort="$sort" :direction="$direction">Login Terakhir</x-sf.sortable-th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        @php $isActive = strtoupper((string) $user->status) === 'ACTIVE'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @if($user->id !== auth()->id())
                                    <input type="checkbox" x-model="selected" value="{{ $user->id }}" class="rounded border-gray-300 text-primary-700">
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" class="w-9 h-9 rounded-full object-cover shrink-0" alt="{{ $user->name }}">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><span class="badge-active">{{ $user->primary_role }}</span></td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $user->outlet?->name ?? '-' }}
                                @if($user->outlets->count() > 1)
                                    <span class="text-xs text-gray-400">+{{ $user->outlets->count() - 1 }} lainnya</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($isActive)
                                    <span class="badge-active">Aktif</span>
                                @else
                                    <span class="badge-draft">Non-Aktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ $user->last_login_at?->diffForHumans() ?? 'Belum pernah' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-btn
                                        icon="edit"
                                        label="Edit {{ $user->name }}"
                                        color="blue"
                                        size="sm"
                                        href="{{ route('settings.users.edit', $user) }}"
                                    />

                                    @if($user->id !== auth()->id())
                                        <x-icon-btn
                                            icon="toggle"
                                            :label="($isActive ? 'Non-Aktifkan ' : 'Aktifkan ').$user->name"
                                            :color="$isActive ? 'red' : 'green'"
                                            size="sm"
                                            href="{{ route('settings.users.toggle-status', $user) }}"
                                            method="PATCH"
                                        />
                                    @endif

                                    <x-icon-btn
                                        icon="config"
                                        label="Reset Password {{ $user->name }}"
                                        color="amber"
                                        size="sm"
                                        href="{{ route('settings.users.reset-password', $user) }}"
                                        method="POST"
                                        confirm="Reset password {{ $user->name }}?"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
                                Belum ada user.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3 flex items-center justify-between gap-2 flex-wrap">
            <x-sf.per-page-selector :options="$perPageOptions" :current="$perPage" />
            {{ $users->links() }}
        </div>
    </div>

</div>

{{-- Bar aksi massal tunggal dgn dropdown pilihan -- BUKAN beberapa bar
     fixed terpisah (akan tumpang-tindih kalau lebih dari 1 aktif
     bersamaan, karena semuanya posisinya sama: fixed bottom). Satu
     array `selected` dipakai utk SEMUA aksi -- backend bulkToggleStatus
     yg sudah ada sudah aman menerima campuran user aktif+non-aktif
     sekaligus (dia filter ulang by status saat ini di server). --}}
<div x-show="selected.length > 0" x-cloak
     class="fixed inset-x-0 bottom-16 z-30 border-t border-gray-200 bg-white px-4 py-3 shadow-lg md:bottom-0 md:px-6">
    <form method="POST" :action="actionRoutes[chosenAction]"
          class="mx-auto flex max-w-7xl flex-wrap items-center gap-3"
          @submit="if (chosenAction === 'change_outlet' && ! outletId) { $event.preventDefault(); return; }
                    if (! confirm(confirmMessages[chosenAction].replace('__COUNT__', selected.length))) { $event.preventDefault(); }">
        @csrf
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
        <input type="hidden" name="status" :value="chosenAction === 'activate' ? 'ACTIVE' : 'INACTIVE'" x-show="chosenAction === 'activate' || chosenAction === 'deactivate'">
        <input type="hidden" name="outlet_id" :value="outletId" x-show="chosenAction === 'change_outlet'">

        <p class="text-sm text-gray-600 shrink-0">
            <span class="font-semibold text-gray-900" x-text="selected.length"></span> user terpilih
        </p>

        <select x-model="chosenAction" class="sf-input min-h-11 text-sm w-auto">
            <option value="deactivate">Nonaktifkan</option>
            <option value="activate">Aktifkan</option>
            <option value="change_outlet">Ganti Outlet</option>
            <option value="delete">Hapus (hanya yang belum pernah login)</option>
        </select>

        <select x-model="outletId" x-show="chosenAction === 'change_outlet'" x-cloak class="sf-input min-h-11 text-sm w-auto">
            <option value="">Pilih outlet tujuan...</option>
            @foreach($outlets as $outlet)
                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="sf-btn-primary min-h-11 px-4 ml-auto"
                :class="chosenAction === 'delete' ? 'bg-red-600! hover:bg-red-700!' : ''">
            <i class="ti ti-check text-base" aria-hidden="true"></i>
            Terapkan
        </button>
    </form>
</div>
</div>
@endsection
