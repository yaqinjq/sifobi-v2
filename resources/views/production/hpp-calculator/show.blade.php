@extends('layouts.app')

@section('title', $calc->product_name)

@section('content')
<x-sf.page-header
    title="{{ $calc->product_name }}"
    subtitle="Riwayat Kalkulator HPP &middot; {{ $calc->created_at->format('d M Y H:i') }}"
    back="{{ route('production.hpp-calculator.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    <x-sf.card>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs text-gray-400">Dibuat Oleh</p>
                <p class="text-gray-800 font-medium">{{ $calc->createdBy?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Outlet</p>
                <p class="text-gray-800 font-medium">{{ $calc->outlet?->name ?? '- Tidak spesifik -' }}</p>
            </div>
        </div>
    </x-sf.card>

    <x-sf.card title="Breakdown HPP">
        <div class="divide-y divide-gray-50">
            @foreach($calc->payload_json['ingredients'] ?? [] as $ing)
                <div class="px-3 py-2.5 flex items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800 truncate">{{ $ing['name'] }}</p>
                        <p class="text-xs text-gray-400">
                            {{ rtrim(rtrim((string) $ing['recipe_qty'], '0'), '.') }} {{ $ing['recipe_unit'] }}
                            dari beli {{ rtrim(rtrim((string) $ing['buy_qty'], '0'), '.') }} {{ $ing['buy_unit'] }}
                            @ Rp {{ number_format((float) $ing['buy_price'], 0, ',', '.') }}
                        </p>
                    </div>
                    <span class="font-semibold text-gray-900 shrink-0">Rp {{ number_format((float) $ing['cost'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            @foreach($calc->payload_json['other_costs'] ?? [] as $cost)
                <div class="px-3 py-2.5 flex items-center justify-between gap-3 text-sm">
                    <div>
                        <p class="font-medium text-gray-800">{{ $cost['label'] }}</p>
                        <p class="text-xs text-gray-400">{{ $cost['cost_type'] === 'PRODUCTION' ? 'Biaya Produksi' : 'Biaya Overhead' }}</p>
                    </div>
                    <span class="font-semibold text-gray-900">Rp {{ number_format((float) $cost['amount'], 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-500">
                <span>Total Bahan Baku</span>
                <span>Rp {{ number_format((float) $calc->ingredients_total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Total Biaya Produksi & Overhead</span>
                <span>Rp {{ number_format((float) $calc->other_costs_total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-semibold text-gray-800">
                <span>Total Biaya (per batch)</span>
                <span>Rp {{ number_format((float) $calc->total_cost, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Volume Produksi</span>
                <span>{{ rtrim(rtrim((string) $calc->volume_production, '0'), '.') }} unit</span>
            </div>
            <div class="flex justify-between items-center rounded-xl bg-primary-50 px-3 py-2.5 mt-2">
                <span class="font-bold text-primary-800">HPP per Unit</span>
                <span class="font-bold text-primary-800 text-lg">Rp {{ number_format((float) $calc->hpp_per_unit, 0, ',', '.') }}</span>
            </div>
        </div>
    </x-sf.card>

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
        Ini catatan coba-coba, bukan resep resmi. Kalau angkanya sudah pas, buat resmi lewat <a href="{{ route('production.menus.index') }}" class="underline font-semibold">Menu &amp; Resep</a>.
    </div>
</div>
@endsection
