@extends('layouts.app')

@section('title', 'Edit Role: '.$role->name)

@section('topbar')
<x-sf.page-header
    title="Role: {{ $role->name }}"
    subtitle="Atur permission untuk role ini"
    back="{{ route('settings.roles.index') }}"
/>
@endsection

@section('content')
<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full"
     x-data="roleEditor()">

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.roles.update', $role) }}">
        @csrf
        @method('PUT')

        {{-- Info Role --}}
        <div class="rounded-xl bg-primary-50 border border-primary-100 px-4 py-3 mb-4 flex items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-primary-900 font-mono">{{ $role->name }}</p>
                <p class="text-xs text-primary-600 mt-0.5">
                    {{ $role->permissions->count() }} permission aktif
                    &middot; dipakai oleh {{ $role->users()->count() }} user
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="button"
                        @click="selectAll()"
                        class="sf-btn-secondary text-xs px-3 py-1.5 min-h-0">Pilih Semua</button>
                <button type="button"
                        @click="clearAll()"
                        class="sf-btn-secondary text-xs px-3 py-1.5 min-h-0 text-red-500">Hapus Semua</button>
            </div>
        </div>

        {{-- Permission Matrix per Group --}}
        <div class="space-y-3 mb-4">
            @foreach($groups as $groupName => $perms)
                <x-sf.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-heading font-semibold text-gray-900 text-sm">{{ $groupName }}</h3>
                            <div class="flex gap-2">
                                <button type="button"
                                        @click="selectGroup('{{ collect($perms)->keys()->implode(',') }}')"
                                        class="text-xs text-primary-600 hover:text-primary-800 font-medium px-2 py-1 rounded hover:bg-primary-50 transition-colors">
                                    Pilih semua
                                </button>
                                <button type="button"
                                        @click="clearGroup('{{ collect($perms)->keys()->implode(',') }}')"
                                        class="text-xs text-gray-400 hover:text-red-500 font-medium px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                    Hapus semua
                                </button>
                            </div>
                        </div>
                    </x-slot:header>
                    <div class="px-4 pb-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($perms as $permName => $permLabel)
                            <label class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer transition-colors"
                                   :class="checked['{{ $permName }}'] ? 'border-primary-300 bg-primary-50' : 'border-gray-100 bg-gray-50 hover:bg-gray-100'">
                                <input type="checkbox"
                                       name="permissions[]"
                                       value="{{ $permName }}"
                                       x-model="checked['{{ $permName }}']"
                                       class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 leading-snug">{{ $permLabel }}</p>
                                    <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $permName }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </x-sf.card>
            @endforeach
        </div>

        {{-- Sticky Save --}}
        <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <div class="flex flex-col sm:flex-row gap-2 sm:justify-between sm:items-center">
                <p class="text-sm text-gray-500">
                    <span x-text="countChecked()"></span> permission dipilih
                </p>
                <div class="flex gap-2">
                    <a href="{{ route('settings.roles.index') }}" class="sf-btn-secondary min-h-11 px-4 text-center">Batal</a>
                    <button type="submit" class="sf-btn-primary min-h-11 px-6">Simpan Permission</button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function roleEditor() {
    const initial = @json($role->permissions->pluck('name')->all());

    const checked = {};
    @foreach($groups as $_g => $_perms)
        @foreach($_perms as $permName => $_label)
        checked['{{ $permName }}'] = initial.includes('{{ $permName }}');
        @endforeach
    @endforeach

    return {
        checked,
        countChecked() {
            return Object.values(this.checked).filter(Boolean).length;
        },
        selectAll() {
            Object.keys(this.checked).forEach(k => this.checked[k] = true);
        },
        clearAll() {
            if (!confirm('Hapus semua permission dari role ini?')) return;
            Object.keys(this.checked).forEach(k => this.checked[k] = false);
        },
        selectGroup(permsCsv) {
            permsCsv.split(',').forEach(p => { if (p in this.checked) this.checked[p] = true; });
        },
        clearGroup(permsCsv) {
            permsCsv.split(',').forEach(p => { if (p in this.checked) this.checked[p] = false; });
        },
    };
}
</script>
@endpush
@endsection
