@extends('layouts.app')

@section('title', 'Denah & Meja POS')

@php
    $canManage = auth()->user()->can('manage_pos_layout');
    $shapeClass = [
        \App\Modules\Pos\Models\PosTable::SHAPE_SQUARE => 'rounded-lg w-20 h-20',
        \App\Modules\Pos\Models\PosTable::SHAPE_ROUND  => 'rounded-full w-20 h-20',
        \App\Modules\Pos\Models\PosTable::SHAPE_RECT   => 'rounded-lg w-28 h-16',
    ];
@endphp

@section('content')
<x-sf.page-header
    title="Denah & Meja POS"
    subtitle="{{ $outlet?->name ?? 'Pilih outlet' }}"
    back="{{ route('pos.orders.index', ['outlet_id' => $outlet?->id]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <form method="GET" class="flex items-center gap-2">
        <select name="outlet_id" class="sf-input text-sm" onchange="this.form.submit()">
            @foreach($outlets as $o)
                <option value="{{ $o->id }}" @selected($o->id == $outlet?->id)>{{ $o->name }}</option>
            @endforeach
        </select>
    </form>

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if(!$outlet)
        <x-sf.empty-state icon="🏬" title="Belum ada outlet" description="Buat outlet dulu di Pengaturan sebelum atur denah POS." />
    @else
        @foreach($areas as $area)
            <x-sf.card>
                <x-slot:header>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $area->name }}</h3>
                        <p class="text-xs text-gray-500">{{ $area->tables->count() }} meja</p>
                    </div>
                    @if($canManage)
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="document.getElementById('edit-area-{{ $area->id }}').classList.toggle('hidden')">
                                <i class="ti ti-pencil" aria-hidden="true"></i>
                            </button>
                            <form method="POST" action="{{ route('pos.areas.destroy', $area) }}" onsubmit="return confirm('Hapus area {{ $area->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                </x-slot:header>

                @if($canManage)
                    <div id="edit-area-{{ $area->id }}" class="hidden mb-3 pb-3 border-b border-gray-100">
                        <form method="POST" action="{{ route('pos.areas.update', $area) }}" class="flex gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $area->name }}" class="sf-input text-sm flex-1" required>
                            <button type="submit" class="sf-btn-secondary px-3">Simpan</button>
                        </form>
                    </div>
                @endif

                @if($canManage)
                    <p class="text-xs text-gray-400 mb-2">Geser meja untuk atur posisi di denah.</p>
                    <div class="relative h-96 bg-gray-50 rounded-2xl border border-dashed border-gray-300 overflow-hidden mb-3"
                         x-data="{
                            dragging: null,
                            startDrag(e, tableId) {
                                const el = e.currentTarget;
                                this.dragging = { tableId, el, offsetX: e.clientX - el.offsetLeft, offsetY: e.clientY - el.offsetTop };
                                const onMove = (ev) => {
                                    if (!this.dragging) return;
                                    const x = Math.max(0, ev.clientX - this.dragging.offsetX);
                                    const y = Math.max(0, ev.clientY - this.dragging.offsetY);
                                    this.dragging.el.style.left = x + 'px';
                                    this.dragging.el.style.top = y + 'px';
                                };
                                const onUp = () => {
                                    if (!this.dragging) return;
                                    const posX = parseInt(this.dragging.el.style.left, 10) || 0;
                                    const posY = parseInt(this.dragging.el.style.top, 10) || 0;
                                    fetch(`/pos/tables/${this.dragging.tableId}/position`, {
                                        method: 'PATCH',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                        },
                                        body: JSON.stringify({ pos_x: posX, pos_y: posY }),
                                    });
                                    this.dragging = null;
                                    window.removeEventListener('mousemove', onMove);
                                    window.removeEventListener('mouseup', onUp);
                                };
                                window.addEventListener('mousemove', onMove);
                                window.addEventListener('mouseup', onUp);
                            }
                         }">
                        @foreach($area->tables as $table)
                            <div class="absolute cursor-move select-none flex flex-col items-center justify-center border-2 {{ $table->statusBadgeClass() }} {{ $shapeClass[$table->shape] ?? $shapeClass['SQUARE'] }} text-xs font-semibold"
                                 style="left: {{ $table->pos_x }}px; top: {{ $table->pos_y }}px;"
                                 @mousedown="startDrag($event, {{ $table->id }})">
                                <span>{{ $table->code }}</span>
                                <span class="text-[10px] font-normal">{{ $table->statusLabel() }}</span>
                            </div>
                        @endforeach
                    </div>

                    <button type="button"
                            onclick="document.getElementById('add-table-{{ $area->id }}').classList.toggle('hidden')"
                            class="sf-btn-secondary inline-flex items-center gap-2 px-4">
                        <i class="ti ti-plus text-base" aria-hidden="true"></i>
                        <span>Tambah Meja</span>
                    </button>
                @else
                    <div class="flex flex-wrap gap-3">
                        @foreach($area->tables as $table)
                            @php $activeOrder = $table->orders->first(); @endphp
                            @if($activeOrder)
                                <a href="{{ route('pos.orders.show', $activeOrder) }}"
                                   class="flex flex-col items-center justify-center border-2 {{ $table->statusBadgeClass() }} {{ $shapeClass[$table->shape] ?? $shapeClass['SQUARE'] }} text-xs font-semibold">
                                    <span>{{ $table->code }}</span>
                                    <span class="text-[10px] font-normal">{{ $table->statusLabel() }}</span>
                                </a>
                            @elseif($table->status === \App\Modules\Pos\Models\PosTable::STATUS_AVAILABLE)
                                <a href="{{ route('pos.orders.create', ['outlet_id' => $outlet->id, 'table_id' => $table->id]) }}"
                                   class="flex flex-col items-center justify-center border-2 {{ $table->statusBadgeClass() }} {{ $shapeClass[$table->shape] ?? $shapeClass['SQUARE'] }} text-xs font-semibold hover:opacity-80">
                                    <span>{{ $table->code }}</span>
                                    <span class="text-[10px] font-normal">{{ $table->statusLabel() }}</span>
                                </a>
                            @else
                                <div class="flex flex-col items-center justify-center border-2 {{ $table->statusBadgeClass() }} {{ $shapeClass[$table->shape] ?? $shapeClass['SQUARE'] }} text-xs font-semibold opacity-70">
                                    <span>{{ $table->code }}</span>
                                    <span class="text-[10px] font-normal">{{ $table->statusLabel() }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($canManage)
                    <div id="add-table-{{ $area->id }}" class="hidden mt-3 pt-3 border-t border-gray-100">
                        <form method="POST" action="{{ route('pos.tables.store') }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                            <input type="hidden" name="pos_area_id" value="{{ $area->id }}">
                            <input type="text" name="code" placeholder="Kode meja" class="sf-input text-sm w-28" required>
                            <input type="number" name="capacity" placeholder="Kapasitas" class="sf-input text-sm w-24" min="1">
                            <select name="shape" class="sf-input text-sm w-28">
                                <option value="SQUARE">Kotak</option>
                                <option value="ROUND">Bulat</option>
                                <option value="RECT">Persegi Panjang</option>
                            </select>
                            <button type="submit" class="sf-btn-primary px-4">Tambah</button>
                        </form>
                    </div>
                @endif
            </x-sf.card>
        @endforeach

        @if($unassignedTables->isNotEmpty() || $canManage)
            <x-sf.card title="Tanpa Area">
                <div class="flex flex-wrap gap-3">
                    @foreach($unassignedTables as $table)
                        <div class="flex flex-col items-center justify-center border-2 {{ $table->statusBadgeClass() }} {{ $shapeClass[$table->shape] ?? $shapeClass['SQUARE'] }} text-xs font-semibold">
                            <span>{{ $table->code }}</span>
                            <span class="text-[10px] font-normal">{{ $table->statusLabel() }}</span>
                        </div>
                    @endforeach
                </div>
            </x-sf.card>
        @endif

        @if($canManage)
            <x-sf.card title="Tambah Area Baru">
                <form method="POST" action="{{ route('pos.areas.store') }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                    <input type="text" name="name" placeholder="Nama area (mis. Lantai 1, Outdoor)" class="sf-input text-sm flex-1" required>
                    <button type="submit" class="sf-btn-primary px-4">Tambah</button>
                </form>
            </x-sf.card>
        @endif
    @endif
</div>
@endsection
