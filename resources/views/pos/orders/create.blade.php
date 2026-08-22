@extends('layouts.app')

@section('title', 'Order Baru')

@section('content')
<x-sf.page-header
    title="Order Baru"
    subtitle="Pilih tipe order dan meja (jika dine-in)"
    back="{{ route('pos.orders.index', ['outlet_id' => $outletId]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    <x-sf.card>
        <form method="POST" action="{{ route('pos.orders.store') }}" class="space-y-4" x-data="{ type: '{{ old('order_type', 'DINE_IN') }}' }">
            @csrf

            <x-sf.form-group label="Outlet" for="outlet_id" :required="true">
                <select id="outlet_id" name="outlet_id" class="sf-input text-base" required>
                    @foreach($outlets as $o)
                        <option value="{{ $o->id }}" @selected(old('outlet_id', $outletId) == $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Tipe Order" for="order_type" :required="true">
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 cursor-pointer transition-colors"
                           :class="type === 'DINE_IN' ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-gray-200 text-gray-500'">
                        <input type="radio" name="order_type" value="DINE_IN" x-model="type" class="sr-only">
                        <span class="text-sm font-semibold">Dine-in</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 cursor-pointer transition-colors"
                           :class="type === 'TAKEAWAY' ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-gray-200 text-gray-500'">
                        <input type="radio" name="order_type" value="TAKEAWAY" x-model="type" class="sr-only">
                        <span class="text-sm font-semibold">Takeaway</span>
                    </label>
                </div>
            </x-sf.form-group>

            <div x-show="type === 'DINE_IN'" x-cloak>
                <x-sf.form-group label="Meja" for="pos_table_id">
                    <select id="pos_table_id" name="pos_table_id" class="sf-input text-base">
                        <option value="">Pilih meja kosong</option>
                        @foreach($tables as $table)
                            <option value="{{ $table->id }}" @selected(old('pos_table_id', $preselectedTableId) == $table->id)>{{ $table->code }} @if($table->capacity)({{ $table->capacity }} org)@endif</option>
                        @endforeach
                    </select>
                    @if($tables->isEmpty())
                        <p class="text-xs text-amber-600 mt-1">Tidak ada meja kosong di outlet ini.</p>
                    @endif
                </x-sf.form-group>
            </div>

            <x-sf.form-group label="Catatan (opsional)" for="notes">
                <textarea id="notes" name="notes" rows="2" class="sf-input text-base" maxlength="500">{{ old('notes') }}</textarea>
            </x-sf.form-group>

            @if($errors->any())
                <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit" class="sf-btn-primary w-full min-h-12">Mulai Order</button>
        </form>
    </x-sf.card>
</div>
@endsection
