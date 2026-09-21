@extends('layouts.app')

@section('title', 'Input Opname Historis')

@section('content')
<x-sf.page-header title="Input Opname Historis" subtitle="Untuk mencatat opname yang terlewat" back="{{ route('operations.opname.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-4 flex items-start gap-2">
        <i class="ti ti-alert-triangle text-base mt-0.5" aria-hidden="true"></i>
        <span>Halaman ini khusus untuk mencatat data opname yang <strong>terlewat pada tanggal sebelumnya</strong>, bukan opname harian normal. Opname harian biasa selalu memakai tanggal hari ini — gunakan menu "Mulai Opname".</span>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('operations.opname.store-historical') }}" class="space-y-4">
        @csrf
        <x-sf.card title="Sesi Opname Historis">
            <div class="space-y-4">
                <div>
                    <label class="sf-label">Outlet *</label>
                    @if($canChangeOutlet)
                        <select name="outlet_id" class="sf-input text-base min-h-11" required>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $defaultOutletId) === (string) $outlet->id)>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="sf-input text-base min-h-11 flex items-center bg-gray-50 text-gray-700">
                            {{ $outlets->first()?->name ?? '-' }}
                        </div>
                        <input type="hidden" name="outlet_id" value="{{ $defaultOutletId }}">
                    @endif
                </div>
                <div>
                    <label class="sf-label">Departemen *</label>
                    @if($canChangeDepartment)
                        <select name="department_id" class="sf-input text-base min-h-11" required>
                            <option value="">Pilih departemen</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="sf-input text-base min-h-11 flex items-center bg-gray-50 text-gray-700">
                            {{ $departments->first()?->name ?? '-' }}
                        </div>
                        <input type="hidden" name="department_id" value="{{ $defaultDepartmentId }}">
                    @endif
                </div>
                <div>
                    <label class="sf-label">Tanggal Opname (Historis) *</label>
                    <input type="date" name="opname_date" value="{{ old('opname_date') }}" max="{{ now()->toDateString() }}" class="sf-input text-base min-h-11" required>
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
                    <label class="sf-label">Alasan / Catatan *</label>
                    <textarea name="notes" rows="3" class="sf-input text-base" placeholder="Jelaskan kenapa opname tanggal ini baru diinput sekarang" required>{{ old('notes') }}</textarea>
                </div>
            </div>
        </x-sf.card>

        <div class="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-white border-t border-gray-100 lg:static lg:mx-0 lg:px-0 lg:border-0 lg:bg-transparent"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Simpan Opname Historis</button>
        </div>
    </form>
</div>
@endsection
