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

            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="{{ route('settings.users.edit', $user) }}" class="sf-btn-secondary text-sm text-center min-h-11">
                    Edit
                </a>

                @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('settings.users.toggle-status', $user) }}">
                        @csrf
                        @method('PATCH')
                        @if($isActive)
                            <button type="submit" class="w-full min-h-11 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600">
                                Non-Aktifkan
                            </button>
                        @else
                            <button type="submit" class="w-full min-h-11 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700">
                                Aktifkan
                            </button>
                        @endif
                    </form>
                @else
                    <button type="button" class="sf-btn-secondary text-sm min-h-11" disabled>
                        Akun Anda
                    </button>
                @endif
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
                                    <a href="{{ route('settings.users.edit', $user) }}"
                                       class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100"
                                       title="Edit"
                                       aria-label="Edit {{ $user->name }}">
                                        <span class="text-sm font-bold">E</span>
                                    </a>

                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('settings.users.toggle-status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if($isActive)
                                                <button type="submit"
                                                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600 hover:bg-red-100"
                                                        title="Non-Aktifkan"
                                                        aria-label="Non-Aktifkan {{ $user->name }}">
                                                    <span class="text-sm font-bold">-</span>
                                                </button>
                                            @else
                                                <button type="submit"
                                                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-700 hover:bg-green-100"
                                                        title="Aktifkan"
                                                        aria-label="Aktifkan {{ $user->name }}">
                                                    <span class="text-sm font-bold">+</span>
                                                </button>
                                            @endif
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('settings.users.reset-password', $user) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700 hover:bg-amber-100"
                                                title="Reset Password"
                                                aria-label="Reset password {{ $user->name }}">
                                            <span class="text-sm font-bold">R</span>
                                        </button>
                                    </form>
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
