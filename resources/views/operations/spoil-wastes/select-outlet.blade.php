@extends('layouts.app')

@section('title', 'Pilih Outlet Aktif — Spoil & Waste')

@section('content')
<x-sf.page-header title="Pilih Outlet Aktif" subtitle="Spoil & Waste" back="{{ route('operations.spoil-wastes.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full space-y-4">
    <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
        Akun Anda terdaftar di beberapa outlet. Pilih 1 outlet aktif untuk mencatat/melihat Spoil & Waste --
        bisa diganti kapan saja lewat tombol "Ganti Outlet".
    </div>

    <x-sf.card>
        <form method="POST" action="{{ route('operations.spoil-wastes.set-outlet') }}" class="space-y-3 p-1">
            @csrf
            @foreach($outlets as $outlet)
                <label class="flex items-center gap-3 rounded-xl border border-gray-100 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors">
                    <input type="radio" name="outlet_id" value="{{ $outlet->id }}" class="text-primary-700"
                           @checked($activeOutletId === $outlet->id) required>
                    <span class="font-semibold text-gray-900">{{ $outlet->name }}</span>
                </label>
            @endforeach

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit" class="sf-btn-primary w-full text-base">Pakai Outlet Ini</button>
        </form>
    </x-sf.card>
</div>
@endsection
