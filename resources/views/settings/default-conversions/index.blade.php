@extends('layouts.app')

@section('title', 'Konversi Default per Satuan')

@section('content')
<x-sf.page-header
    title="Konversi Default per Satuan"
    subtitle="Konversi ini otomatis diterapkan saat item baru dibuat dengan satuan yang sesuai"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">

    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="ti ti-alert-triangle mr-1"></i> {{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Info box --}}
    <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 flex gap-3">
        <i class="ti ti-info-circle mt-0.5 shrink-0 text-blue-500"></i>
        <div>
            Konversi di bawah ini akan <strong>otomatis ditambahkan</strong> ke setiap item baru
            yang satuannya cocok dengan kolom <em>Dari Satuan</em>. Contoh: tambahkan
            <strong>KG → Gram (1000)</strong>, maka setiap item baru ber-satuan KG langsung
            memiliki baris konversi KG→Gram tanpa perlu diisi manual.
        </div>
    </div>

    {{-- List --}}
    <x-sf.card title="Daftar Konversi Default">
        @if($defaults->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-400">
                Belum ada konversi default. Tambahkan di bawah.
            </p>
        @else
            <div class="hidden lg:grid grid-cols-[1fr_1fr_1fr_auto] gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                <span>Dari Satuan</span>
                <span>Ke Satuan</span>
                <span>Faktor (×)</span>
                <span class="text-right">Aksi</span>
            </div>

            <div class="divide-y divide-gray-50">
                @foreach($defaults as $default)
                    <div class="grid grid-cols-1 lg:grid-cols-[1fr_1fr_1fr_auto] gap-3 items-center px-3 py-3">
                        <div>
                            <span class="lg:hidden text-xs font-semibold text-gray-400 uppercase mr-2">Dari:</span>
                            <span class="font-semibold text-gray-900">{{ $default->fromUnit?->code ?? '—' }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $default->fromUnit?->name }}</span>
                        </div>
                        <div>
                            <span class="lg:hidden text-xs font-semibold text-gray-400 uppercase mr-2">Ke:</span>
                            <span class="font-semibold text-gray-900">{{ $default->toUnit?->code ?? '—' }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $default->toUnit?->name }}</span>
                        </div>
                        <div>
                            <span class="lg:hidden text-xs font-semibold text-gray-400 uppercase mr-2">Faktor:</span>
                            <span class="font-mono text-gray-800">
                                × {{ rtrim(rtrim((string)($default->factor ?? $default->multiply_rate), '0'), '.') }}
                            </span>
                            <span class="text-xs text-gray-400 ml-1">
                                (1 {{ $default->fromUnit?->code }} = {{ rtrim(rtrim((string)($default->factor ?? $default->multiply_rate), '0'), '.') }} {{ $default->toUnit?->code }})
                            </span>
                        </div>
                        <div class="flex justify-end">
                            <form method="POST" action="{{ route('settings.default-conversions.destroy', $default->id) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn
                                    icon="delete"
                                    label="Hapus"
                                    color="red"
                                    type="submit"
                                    onclick="return confirm('Hapus konversi default {{ $default->fromUnit?->code }} → {{ $default->toUnit?->code }}?')"
                                />
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>

    {{-- Preset tangga --}}
    <x-sf.card title="Muat Preset Tangga Konversi">
        <div class="p-4 space-y-3">
            <p class="text-sm text-gray-500">
                Sistem akan mencari satuan yang cocok berdasarkan kode (KG, GR, L, ML, dll).
                Pasangan yang satuannya belum ada di master data akan dilewati.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                {{-- Tangga Massa --}}
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 space-y-2">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="ti ti-weight text-amber-600 text-lg"></i>
                        <span class="font-semibold text-amber-800 text-sm">Tangga Massa</span>
                    </div>
                    <ul class="text-xs text-amber-700 space-y-0.5 pl-1">
                        <li>KG → Gram (× 1.000)</li>
                        <li>Gram → mg (× 1.000)</li>
                        <li>KG → mg (× 1.000.000)</li>
                        <li>Ton → KG (× 1.000)</li>
                        <li>Ton → Gram (× 1.000.000)</li>
                    </ul>
                    <form method="POST" action="{{ route('settings.default-conversions.preset', 'massa') }}" class="pt-1">
                        @csrf
                        <button type="submit" class="w-full text-center text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-3 py-2 transition"
                            onclick="return confirm('Muat preset Tangga Massa? Konversi yang sudah ada akan diperbarui.')">
                            <i class="ti ti-download"></i> Muat Tangga Massa
                        </button>
                    </form>
                </div>

                {{-- Tangga Volume --}}
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 space-y-2">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="ti ti-droplet text-blue-600 text-lg"></i>
                        <span class="font-semibold text-blue-800 text-sm">Tangga Volume</span>
                    </div>
                    <ul class="text-xs text-blue-700 space-y-0.5 pl-1">
                        <li>Liter → mL (× 1.000)</li>
                        <li>Liter → dL (× 10)</li>
                        <li>Liter → cL (× 100)</li>
                        <li>dL → mL (× 100)</li>
                        <li>cL → mL (× 10)</li>
                    </ul>
                    <form method="POST" action="{{ route('settings.default-conversions.preset', 'volume') }}" class="pt-1">
                        @csrf
                        <button type="submit" class="w-full text-center text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-2 transition"
                            onclick="return confirm('Muat preset Tangga Volume? Konversi yang sudah ada akan diperbarui.')">
                            <i class="ti ti-download"></i> Muat Tangga Volume
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </x-sf.card>

    {{-- Add form --}}
    <x-sf.card title="Tambah Konversi Default">
        <form method="POST" action="{{ route('settings.default-conversions.store') }}" class="p-4 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="sf-label" for="from_unit_id">Dari Satuan</label>
                    <select name="from_unit_id" id="from_unit_id" class="sf-input" required>
                        <option value="">— pilih satuan —</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('from_unit_id') == $unit->id)>
                                {{ $unit->code }} — {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sf-label" for="to_unit_id">Ke Satuan</label>
                    <select name="to_unit_id" id="to_unit_id" class="sf-input" required>
                        <option value="">— pilih satuan —</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('to_unit_id') == $unit->id)>
                                {{ $unit->code }} — {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sf-label" for="factor">Faktor Konversi</label>
                    <input
                        type="number"
                        name="factor"
                        id="factor"
                        class="sf-input"
                        placeholder="cth: 1000"
                        step="any"
                        min="0.00000001"
                        value="{{ old('factor') }}"
                        required
                    >
                    <p class="text-xs text-gray-400 mt-1">1 [Dari Satuan] = faktor [Ke Satuan]</p>
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <button type="submit" class="sf-btn-primary px-5">
                    <i class="ti ti-plus"></i> Tambah
                </button>
            </div>
        </form>
    </x-sf.card>

</div>
@endsection
