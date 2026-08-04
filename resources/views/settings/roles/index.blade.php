@extends('layouts.app')

@section('title', 'Manajemen Role')

@section('topbar')
<x-sf.page-header
    title="Manajemen Role"
    subtitle="Atur role dan permission akses per fitur"
    back="{{ route('settings.index') }}"
/>
@endsection

@section('content')
<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-5">

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Buat Role Baru --}}
    <x-sf.card title="Buat Role Baru">
        <form method="POST" action="{{ route('settings.roles.store') }}" class="px-4 pb-4 flex flex-col sm:flex-row gap-3">
            @csrf
            <div class="flex-1">
                <input type="text"
                       name="name"
                       value="{{ old('name') }}"
                       placeholder="Nama role, mis: STAFF_PASTRY"
                       class="sf-input text-base min-h-11 uppercase"
                       style="text-transform:uppercase"
                       oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_]/g,'')"
                       maxlength="60" required>
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">Gunakan HURUF_KAPITAL dan underscore saja. Contoh: STAFF_PASTRY, MANAGER_PRODUKSI</p>
            </div>
            <button type="submit" class="sf-btn-primary min-h-11 px-6 shrink-0">+ Tambah Role</button>
        </form>
    </x-sf.card>

    {{-- Matriks Overview --}}
    <x-sf.card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <h3 class="font-heading font-bold text-gray-900 text-sm">Matriks Permission per Role</h3>
                <span class="text-xs text-gray-400">{{ $roles->count() }} role terdaftar</span>
            </div>
        </x-slot:header>

        <div class="overflow-x-auto">
            <table class="w-full text-xs" style="min-width: {{ 180 + ($roles->count() * 110) }}px">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-4 py-3 font-semibold text-gray-500 w-44 bg-gray-50 sticky left-0 z-10">Grup / Permission</th>
                        @foreach($roles as $role)
                            <th class="text-center px-2 py-3 font-semibold text-gray-700 min-w-[100px]">
                                <a href="{{ route('settings.roles.edit', $role) }}"
                                   class="inline-block hover:text-primary-700 transition-colors">
                                    <span class="block font-mono text-xs">{{ $role->name }}</span>
                                    <span class="block text-gray-400 font-normal mt-0.5">{{ $role->users_count }} user</span>
                                </a>
                            </th>
                        @endforeach
                        <th class="px-2 py-3 text-center text-gray-400 font-normal w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $groupName => $perms)
                        {{-- Group header row --}}
                        <tr class="bg-primary-50/60">
                            <td colspan="{{ $roles->count() + 2 }}"
                                class="px-4 py-2 font-semibold text-primary-800 text-xs uppercase tracking-wide sticky left-0">
                                {{ $groupName }}
                            </td>
                        </tr>
                        {{-- Permission rows --}}
                        @foreach($perms as $permName => $permLabel)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-2.5 text-gray-600 bg-white sticky left-0 z-10 border-r border-gray-100">
                                    <span class="block font-medium text-gray-700">{{ $permLabel }}</span>
                                    <span class="block text-gray-400 font-mono text-[10px] mt-0.5">{{ $permName }}</span>
                                </td>
                                @foreach($roles as $role)
                                    <td class="text-center px-2 py-2.5">
                                        @if($role->hasPermissionTo($permName))
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-700" title="Diizinkan">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-6 h-6 text-gray-200" title="Tidak diizinkan">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-center px-2 py-2.5 text-gray-200">—</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 bg-gray-50">
                        <td class="px-4 py-3 font-semibold text-gray-500 sticky left-0 bg-gray-50 z-10">Aksi</td>
                        @foreach($roles as $role)
                            <td class="text-center px-2 py-3">
                                <div class="flex flex-col gap-1 items-center">
                                    <a href="{{ route('settings.roles.edit', $role) }}"
                                       class="inline-flex items-center gap-1 sf-btn-secondary text-xs px-2.5 py-1.5 min-h-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    @if($role->users_count === 0)
                                        <form method="POST" action="{{ route('settings.roles.destroy', $role) }}"
                                              onsubmit="return confirm('Hapus role {{ $role->name }}? Tindakan ini tidak bisa dibatalkan.')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 px-2.5 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Hapus
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-gray-400">{{ $role->users_count }} user aktif</span>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-4 text-xs text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="inline-flex w-5 h-5 rounded-full bg-green-100 text-green-700 items-center justify-center">✓</span>
                Diizinkan
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-flex w-5 h-5 text-gray-200 items-center justify-center">✗</span>
                Tidak diizinkan
            </span>
            <span class="ml-auto">Klik nama role untuk edit permission-nya</span>
        </div>
    </x-sf.card>

</div>
@endsection
