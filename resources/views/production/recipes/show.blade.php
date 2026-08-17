@extends('layouts.app')

@section('title', 'Resep — '.$recipe->menu->name)

@section('content')
<x-sf.page-header
    title="{{ $recipe->menu->name }} — Versi {{ $recipe->version_number }}"
    subtitle="{{ $recipe->menu->brand?->name ?? '-' }}"
    back="{{ route('production.menus.show', $recipe->menu) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5" x-data="{ approveModal: false, rejectModal: false }">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-sf.card>
        <div class="flex items-center justify-between mb-3">
            <span class="{{ $recipe->statusBadgeClass() }}">{{ $recipe->statusLabel() }}</span>
            <span class="text-xs text-gray-400">{{ $recipe->created_at->format('d M Y H:i') }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs text-gray-400">Dibuat Oleh</p>
                <p class="text-gray-800 font-medium">{{ $recipe->createdBy?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Tanggal Tes/Food Panel</p>
                <p class="text-gray-800 font-medium">{{ optional($recipe->test_date)->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Disaksikan Oleh</p>
                <p class="text-gray-800">
                    {{ $recipe->witnessed_by_user_ids ? \App\Models\User::whereIn('id', $recipe->witnessed_by_user_ids)->pluck('name')->join(', ') : '' }}
                    @if($recipe->witnessed_by_user_ids && $recipe->witnessed_by_names) &middot; @endif
                    {{ $recipe->witnessed_by_names }}
                    @if(!$recipe->witnessed_by_user_ids && !$recipe->witnessed_by_names) - @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Food Panel Oleh</p>
                <p class="text-gray-800">
                    {{ $recipe->food_panel_user_ids ? \App\Models\User::whereIn('id', $recipe->food_panel_user_ids)->pluck('name')->join(', ') : '' }}
                    @if($recipe->food_panel_user_ids && $recipe->food_panel_names) &middot; @endif
                    {{ $recipe->food_panel_names }}
                    @if(!$recipe->food_panel_user_ids && !$recipe->food_panel_names) - @endif
                </p>
            </div>
        </div>
        @if($recipe->notes)
            <p class="text-xs text-gray-500 mt-3 italic">{{ $recipe->notes }}</p>
        @endif
        @if($recipe->status === \App\Modules\Production\Models\Recipe::STATUS_REJECTED && $recipe->rejected_reason)
            <div class="mt-3 rounded-xl bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
                <strong>Alasan ditolak:</strong> {{ $recipe->rejected_reason }}
            </div>
        @endif
        @if($recipe->status === \App\Modules\Production\Models\Recipe::STATUS_APPROVED && $recipe->outlets->isNotEmpty())
            <div class="mt-3">
                <p class="text-xs text-gray-400 mb-1">Diterapkan di outlet</p>
                <div class="flex flex-wrap gap-1">
                    @foreach($recipe->outlets as $outlet)
                        <span class="badge-active text-xs">{{ $outlet->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Breakdown HPP">
        <div class="divide-y divide-gray-50">
            @foreach($hpp['ingredient_rows'] as $row)
                @php $ing = $row['ingredient']; @endphp
                <div class="px-3 py-2.5 flex items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800 truncate">{{ $ing->item?->name ?? '-' }}</p>
                        <p class="text-xs text-gray-400">
                            {{ rtrim(rtrim((string) $ing->recipe_qty, '0'), '.') }} {{ $ing->recipeUnit?->code }}
                            dari beli {{ rtrim(rtrim((string) $ing->buy_qty, '0'), '.') }} {{ $ing->buyUnit?->code }}
                            @ Rp {{ number_format((float) $ing->buy_price, 0, ',', '.') }}
                        </p>
                    </div>
                    <span class="font-semibold text-gray-900 shrink-0">Rp {{ number_format((float) $row['cost'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            @foreach($hpp['other_cost_rows'] as $cost)
                <div class="px-3 py-2.5 flex items-center justify-between gap-3 text-sm">
                    <div>
                        <p class="font-medium text-gray-800">{{ $cost->label }}</p>
                        <p class="text-xs text-gray-400">{{ \App\Modules\Production\Models\RecipeOtherCost::TYPE_LABELS[$cost->cost_type] ?? $cost->cost_type }}</p>
                    </div>
                    <span class="font-semibold text-gray-900">Rp {{ number_format((float) $cost->amount, 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-500">
                <span>Total Bahan Baku</span>
                <span>Rp {{ number_format((float) $hpp['ingredients_total'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Total Biaya Produksi & Overhead</span>
                <span>Rp {{ number_format((float) $hpp['other_costs_total'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-semibold text-gray-800">
                <span>Total Biaya (per batch)</span>
                <span>Rp {{ number_format((float) $hpp['total_cost'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Volume Produksi</span>
                <span>{{ rtrim(rtrim((string) $hpp['volume_production'], '0'), '.') }} unit</span>
            </div>
            <div class="flex justify-between items-center rounded-xl bg-primary-50 px-3 py-2.5 mt-2">
                <span class="font-bold text-primary-800">HPP per Unit</span>
                <span class="font-bold text-primary-800 text-lg">Rp {{ number_format((float) $hpp['hpp_per_unit'], 0, ',', '.') }}</span>
            </div>
        </div>
    </x-sf.card>

    @if($recipe->approvalEvents->isNotEmpty())
        <x-sf.card title="Riwayat Persetujuan">
            <div class="divide-y divide-gray-50">
                @foreach($recipe->approvalEvents as $event)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800">{{ $event->eventLabel() }}</span>
                            <span class="text-xs text-gray-400">{{ $event->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">oleh {{ $event->actor?->name ?? '—' }}</p>
                        @if($event->notes)
                            <p class="text-xs text-gray-400 mt-1 italic">{{ $event->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    <div class="space-y-3">
        @if($recipe->canEdit())
            <a href="{{ route('production.recipes.edit', $recipe) }}" class="sf-btn-secondary w-full flex items-center justify-center min-h-12">Edit Draft</a>
        @endif

        @if($recipe->canSubmit())
            <form method="POST" action="{{ route('production.recipes.submit', $recipe) }}">
                @csrf
                <button type="submit" class="sf-btn-primary w-full min-h-12">Ajukan untuk Persetujuan</button>
            </form>
        @endif

        @can('approve_recipes')
            @if($recipe->canApprove())
                <button type="button" @click="approveModal = true" class="sf-btn-primary w-full min-h-12">Setujui & Terapkan ke Outlet</button>
                <button type="button" @click="rejectModal = true" class="sf-btn-secondary w-full min-h-12">Tolak</button>
            @endif
        @endcan
    </div>

    {{-- ── APPROVE MODAL ── --}}
    <div x-show="approveModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40" @keydown.escape.window="approveModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full sm:max-w-md max-h-[90vh] max-h-[90dvh] flex flex-col overflow-hidden" @click.stop>
            <div class="p-6 pb-4 shrink-0">
                <h3 class="font-heading font-bold text-gray-900 text-lg mb-1">Setujui Resep</h3>
                <p class="text-sm text-gray-500">Pilih outlet yang akan menerapkan resep versi ini. Outlet yang sudah pakai versi lain untuk menu ini akan otomatis dipindah ke versi ini.</p>
            </div>
            <form method="POST" action="{{ route('production.recipes.approve', $recipe) }}" class="flex flex-col flex-1 min-h-0">
                @csrf
                <div class="px-6 overflow-y-auto flex-1 min-h-0 space-y-2">
                    @foreach($outlets as $outlet)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="outlet_ids[]" value="{{ $outlet->id }}" class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                            {{ $outlet->name }}
                        </label>
                    @endforeach
                </div>
                <div class="p-6 pt-4 flex gap-3 shrink-0 border-t border-gray-100">
                    <button type="button" @click="approveModal = false" class="sf-btn-secondary flex-1">Batal</button>
                    <button type="submit" class="sf-btn-primary flex-1">Setujui</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── REJECT MODAL ── --}}
    <div x-show="rejectModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40" @keydown.escape.window="rejectModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full sm:max-w-md" @click.stop>
            <div class="p-6">
                <h3 class="font-heading font-bold text-gray-900 text-lg mb-1">Tolak Resep?</h3>
                <p class="text-sm text-gray-500 mb-4">Berikan alasan penolakan.</p>
                <form method="POST" action="{{ route('production.recipes.reject', $recipe) }}" class="space-y-4">
                    @csrf
                    <x-sf.form-group label="Alasan Penolakan" for="rejected_reason" :required="true">
                        <textarea id="rejected_reason" name="rejected_reason" rows="3" required minlength="5" class="sf-input resize-none"></textarea>
                    </x-sf.form-group>
                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="rejectModal = false" class="sf-btn-secondary flex-1">Batal</button>
                        <button type="submit" class="sf-btn-danger flex-1">Ya, Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
