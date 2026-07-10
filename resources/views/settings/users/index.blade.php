@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<x-sf.page-header title="Manajemen User" subtitle="Kelola akun pengguna sistem">
    <x-slot:actions>
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
                    <span class="badge-draft">{{ $user->outlet->name }}</span>
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

    {{ $users->links() }}
</div>

<div class="hidden md:block p-6 pb-8">
    <div class="sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Login Terakhir</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        @php $isActive = strtoupper((string) $user->status) === 'ACTIVE'; @endphp
                        <tr class="hover:bg-gray-50">
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
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $user->outlet?->name ?? '-' }}</td>
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
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">
                                Belum ada user.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
