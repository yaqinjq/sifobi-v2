@extends('layouts.app')

@section('title', 'Kalender Event')

@section('content')
<x-sf.page-header
    title="Kalender Event"
    subtitle="Hari raya, promo, dan faktor perubahan demand"
    back="{{ auth()->user()->can('manage_settings') ? route('settings.index') : route('dashboard') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Multiplier <strong>1.5</strong> berarti demand naik 50%, sedangkan <strong>0.8</strong> berarti turun 20%.
    </div>

    <x-sf.card title="Daftar Event">
        <div class="hidden lg:grid grid-cols-[1.5fr_1fr_1.3fr_1fr_0.8fr_auto] gap-3 px-3 py-2 border-b border-gray-100 text-xs font-semibold uppercase text-gray-500">
            <span>Nama Event</span>
            <span>Tanggal</span>
            <span>Outlet / Brand</span>
            <span>Tipe</span>
            <span>Multiplier</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($events as $event)
                @php
                    $typeClass = [
                        'HARI_RAYA' => 'badge-active',
                        'PROMO' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800',
                        'LIBURAN' => 'badge-pending',
                        'PEAK_SEASON' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800',
                        'CUSTOM' => 'badge-draft',
                    ][$event->event_type] ?? 'badge-draft';
                @endphp
                <div class="py-4" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.calendar-events.update', $event) }}"
                          class="grid grid-cols-1 lg:grid-cols-[1.5fr_1fr_1.3fr_1fr_0.8fr_auto] gap-3 items-center">
                        @csrf
                        @method('PUT')

                        <div>
                            <span class="lg:hidden sf-label">Nama Event</span>
                            <span x-show="!editing" class="font-semibold text-gray-900">{{ $event->name }}</span>
                            <input x-show="editing" x-cloak name="name" value="{{ $event->name }}" class="sf-input text-base" required>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Tanggal</span>
                            <span x-show="!editing" class="text-sm text-gray-700">
                                {{ $event->event_date->format('d M Y') }}
                                @if($event->event_end_date)
                                    - {{ $event->event_end_date->format('d M Y') }}
                                @endif
                            </span>
                            <div x-show="editing" x-cloak class="space-y-2">
                                <input type="date" name="event_date" value="{{ $event->event_date->toDateString() }}" class="sf-input text-base" required>
                                <input type="date" name="event_end_date" value="{{ $event->event_end_date?->toDateString() }}" class="sf-input text-base">
                            </div>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Outlet / Brand</span>
                            <span x-show="!editing" class="text-sm text-gray-700">
                                {{ $event->outlet?->name ?? $event->brand?->name ?? 'Semua outlet' }}
                            </span>
                            <div x-show="editing" x-cloak class="space-y-2">
                                <select name="outlet_id" class="sf-input text-base">
                                    <option value="">Semua outlet</option>
                                    @foreach($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected($event->outlet_id === $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                                <select name="brand_id" class="sf-input text-base">
                                    <option value="">Semua brand</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected($event->brand_id === $brand->id)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Tipe</span>
                            <span x-show="!editing" class="{{ $typeClass }}">{{ str_replace('_', ' ', $event->event_type) }}</span>
                            <select x-show="editing" x-cloak name="event_type" class="sf-input text-base" required>
                                @foreach($eventTypes as $type)
                                    <option value="{{ $type }}" @selected($event->event_type === $type)>{{ str_replace('_', ' ', $type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Multiplier</span>
                            <span x-show="!editing" class="font-semibold text-gray-900">{{ number_format((float) $event->demand_multiplier, 2) }}x</span>
                            <div x-show="editing" x-cloak class="space-y-2">
                                <input type="number" name="demand_multiplier" min="0.01" max="99.99" step="0.01"
                                       value="{{ $event->demand_multiplier }}" class="sf-input text-base" required>
                                <label class="flex min-h-11 items-center gap-2 text-sm">
                                    <input type="checkbox" name="is_active" value="1" @checked($event->is_active)>
                                    Aktif
                                </label>
                                <input type="hidden" name="notes" value="{{ $event->notes }}">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" x-show="!editing" @click="editing = true" class="sf-btn-secondary text-xs">Edit</button>
                            <button type="submit" x-show="editing" x-cloak class="sf-btn-primary text-xs">Simpan</button>
                            <button type="button" x-show="editing" x-cloak @click="editing = false" class="sf-btn-secondary text-xs">Batal</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.calendar-events.destroy', $event) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sf-btn-danger text-xs">Hapus</button>
                    </form>
                </div>
            @empty
                <div class="py-10 text-center text-sm text-gray-500">Belum ada event demand.</div>
            @endforelse
        </div>

        <div class="pt-4">{{ $events->links() }}</div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Event">
        <form method="POST" action="{{ route('settings.calendar-events.store') }}"
              class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 items-end">
            @csrf
            <x-sf.form-group label="Nama Event" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name') }}" class="sf-input text-base" maxlength="255" required>
            </x-sf.form-group>
            <x-sf.form-group label="Tanggal Mulai" for="event_date" :required="true">
                <input id="event_date" name="event_date" type="date" value="{{ old('event_date', now()->toDateString()) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <x-sf.form-group label="Tanggal Selesai" for="event_end_date">
                <input id="event_end_date" name="event_end_date" type="date" value="{{ old('event_end_date') }}" class="sf-input text-base">
            </x-sf.form-group>
            <x-sf.form-group label="Tipe Event" for="event_type" :required="true">
                <select id="event_type" name="event_type" class="sf-input text-base" required>
                    @foreach($eventTypes as $type)
                        <option value="{{ $type }}" @selected(old('event_type') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Demand Multiplier" for="demand_multiplier" hint="1.5 = naik 50%, 0.8 = turun 20%" :required="true">
                <input id="demand_multiplier" name="demand_multiplier" type="number" min="0.01" max="99.99" step="0.01"
                       value="{{ old('demand_multiplier', 1) }}" class="sf-input text-base" required>
            </x-sf.form-group>
            <x-sf.form-group label="Outlet" for="outlet_id" hint="Kosongkan untuk semua outlet">
                <select id="outlet_id" name="outlet_id" class="sf-input text-base">
                    <option value="">Semua outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected(old('outlet_id') == $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Brand" for="brand_id" hint="Kosongkan untuk semua brand">
                <select id="brand_id" name="brand_id" class="sf-input text-base">
                    <option value="">Semua brand</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>
            <x-sf.form-group label="Catatan" for="notes">
                <input id="notes" name="notes" value="{{ old('notes') }}" class="sf-input text-base" maxlength="2000">
            </x-sf.form-group>
            <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                Event aktif
            </label>
            <button type="submit" class="sf-btn-primary w-full sm:col-span-2 xl:col-span-3">Simpan Event</button>
        </form>
    </x-sf.card>
</div>
@endsection
