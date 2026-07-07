@extends('layouts.app')

@section('title', 'Mulai Opname')

@section('content')
<x-sf.page-header title="Mulai Opname Baru" subtitle="Sistem akan membuat snapshot stok saat ini" back="{{ route('operations.opname.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('operations.opname.store') }}" class="space-y-4">
        @csrf
        <x-sf.card title="Sesi Opname">
            <div class="space-y-4">
                <div>
                    <label class="sf-label">Outlet *</label>
                    <select name="outlet_id" class="sf-input text-base min-h-11" required>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $defaultOutletId) === (string) $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Tanggal *</label>
                    <input type="date" name="opname_date" value="{{ old('opname_date', now()->toDateString()) }}" class="sf-input text-base min-h-11" required>
                </div>
                <div>
                    <label class="sf-label">Shift</label>
                    <select name="shift" class="sf-input text-base min-h-11">
                        <option value="">Tanpa shift</option>
                        <option value="PAGI" @selected(old('shift') === 'PAGI')>Pagi</option>
                        <option value="SORE" @selected(old('shift') === 'SORE')>Sore</option>
                        <option value="MALAM" @selected(old('shift') === 'MALAM')>Malam</option>
                    </select>
                </div>
                <div>
                    <label class="sf-label">Catatan</label>
                    <textarea name="notes" rows="3" class="sf-input text-base">{{ old('notes') }}</textarea>
                </div>
            </div>
        </x-sf.card>

        <x-sf.card>
            <p class="text-sm text-gray-700">
                Sistem akan memuat <span class="font-bold text-gray-900">{{ $dailyItemCount }}</span> item yang perlu di-opname harian untuk outlet ini.
            </p>
        </x-sf.card>

        <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Mulai Opname</button>
        </div>
    </form>
</div>
@endsection
