@php
    $item = $opnameItem->item;
    $inventoryUnit = $item?->inventoryUnit?->abbreviation ?? $opnameItem->unit?->abbreviation ?? 'unit';
    $baseUnit = $item?->baseUnit?->abbreviation ?? 'base';
    $invRatio = (float) ($opnameItem->inv_ratio ?? $item?->inventory_ratio ?? 1);
    $sysQtyBase  = (float) $opnameItem->system_qty_base;  // referensi selisih (snapshot saat sesi mulai)
    $sysQtyLive  = (float) ($opnameItem->stok_sistem ?? 0); // live balance (info saja)
    $sysQty      = $sysQtyBase; // tetap expose untuk kompatibilitas isOverSystemStock
    $isDecimalUnit = in_array(strtolower($inventoryUnit), ['gr', 'g', 'kg', 'mg', 'ml', 'l', 'ltr', 'cc', 'dl', 'cl']);
    $decimals = $isDecimalUnit ? 4 : 0;
@endphp
<div id="opname-item-{{ $opnameItem->id }}"
      class="sf-card p-4"
      data-opname-item="1"
      data-item-name="{{ $item?->name ?? '' }}"
      x-show="mode !== 'zoom' || zoomFocusId === {{ $opnameItem->id }}"
      x-cloak
      :style="mode === 'zoom' ? 'view-transition-name: vt-opname-item-{{ $opnameItem->id }}' : ''"
      x-data="opnameItemCard({
         url: @js(route('operations.opname.update-item', [$session, $opnameItem])),
         syncUrl: @js(route('operations.opname.sync-item', [$session, $opnameItem])),
         suggestionUrl: @js(route('api.stock-suggestion', ['item_id' => $opnameItem->item_id, 'outlet_id' => $session->outlet_id])),
         variance: @js((string) $opnameItem->variance),
        varianceValue: @js((string) $opnameItem->variance_value),
        qtyWhole: @js((string) $opnameItem->physical_qty_whole),
        qtyLoose: @js((string) $opnameItem->physical_qty_loose),
        invRatio: @js($invRatio),
        sysQtyBase: @js($sysQtyBase),
        sysQtyLive: @js($sysQtyLive),
        sysQty: @js($sysQtyBase),
        decimals: @js($decimals),
         wasCounted: @js((bool) $opnameItem->is_counted)
      })"
      x-init="fetchSuggestion()"
      x-effect="$el.dataset.overSystem = isOverSystemStock; $el.dataset.suspicious = isSuspiciousWhenZero; $el.dataset.physicalDisplay = physicalBaseDisplay">
    <div class="flex items-start justify-between gap-3">
        <div class="shrink-0">
            @if($item?->photo)
                <img src="{{ asset('storage/'.$item->photo) }}" alt="{{ $item->name }}"
                     class="w-12 h-12 lg:w-14 lg:h-14 rounded-xl object-cover border border-gray-200">
            @else
                <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                    <i class="ti ti-photo text-lg lg:text-xl" aria-hidden="true"></i>
                </div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900">{{ $item?->name ?? '-' }}</p>
            <p class="text-xs text-gray-500">{{ $item?->canonical_sku ?? '-' }}</p>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
            <span class="badge-draft">{{ $opnameItem->department?->name ?? $item?->primaryDepartment?->name ?? '-' }}</span>
            @if(array_key_exists($opnameItem->item_id, $sharedItemIds))
                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700">
                    <i class="ti ti-share text-xs" aria-hidden="true"></i>
                    Item Bersama
                </span>
            @endif
        </div>
    </div>

    <div class="mt-4 space-y-1.5">
        <div class="rounded-xl px-3 py-2 text-sm flex justify-between gap-3 {{ $sysQtyBase > 0 ? 'bg-blue-50' : 'bg-gray-50' }}">
            <span class="{{ $sysQtyBase > 0 ? 'text-blue-600' : 'text-gray-500' }}">Stok Saat Ini</span>
            <span class="font-semibold {{ $sysQtyBase > 0 ? 'text-blue-700' : 'text-gray-400' }}">
                {{ number_format($sysQtyBase / ($invRatio ?: 1), $decimals) }} {{ $inventoryUnit }}
                @if($sysQtyBase > 0)
                    <span class="text-xs font-normal text-gray-400">({{ number_format($sysQtyBase, 0) }} {{ $baseUnit }})</span>
                @endif
            </span>
        </div>
        @if($sysQtyBase == 0)
            <p class="text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1">
                ⚠️ Stok sistem = 0. Jika ada stok fisik, input jumlah yang sebenarnya.
            </p>
        @endif

        @if($session->status === 'DRAFT')
            <div x-show="isBaselineStale" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                <div class="flex items-start gap-2">
                    <i class="ti ti-refresh-alert text-amber-600 shrink-0 mt-0.5 text-sm" aria-hidden="true"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-amber-800">Stok sistem sudah berubah sejak sesi ini dimulai</p>
                        <p class="text-xs text-amber-700 mt-0.5">
                            Baseline sesi: <span x-text="formatQty(sysQtyBase / (invRatio || 1))"></span> {{ $inventoryUnit }}
                            &rarr; Terkini: <span x-text="formatQty(sysQtyLive / (invRatio || 1))"></span> {{ $inventoryUnit }}
                        </p>
                        <button type="button" @click="syncBaseline()" :disabled="syncing"
                                class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 py-1.5 disabled:opacity-50">
                            <i class="ti ti-refresh text-sm" :class="syncing ? 'animate-spin' : ''" aria-hidden="true"></i>
                            <span x-text="syncing ? 'Menyinkronkan...' : 'Sinkronkan Sekarang'"></span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="sf-label">Fisik utuh ({{ $inventoryUnit }})</label>
            <input type="text"
                   inputmode="decimal"
                   x-model="qtyWhole"
                   @input.debounce.500ms="save()"
                   placeholder="0"
                   class="sf-input text-base min-h-11"
                   @disabled($session->status !== 'DRAFT')>
        </div>
        <div>
            <label class="sf-label">Fisik ecer ({{ $baseUnit }})</label>
            <input type="text"
                   inputmode="decimal"
                   x-model="qtyLoose"
                   @input.debounce.500ms="save()"
                   placeholder="0"
                   class="sf-input text-base min-h-11"
                   @disabled($session->status !== 'DRAFT')>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
            <span class="text-gray-500">Fisik base</span>
            <span class="font-semibold text-gray-900"><span x-text="physicalBaseDisplay"></span> {{ $inventoryUnit }}</span>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2 flex justify-between gap-3">
            <span class="text-gray-500">Selisih</span>
            <span>
                <span x-show="(wasCounted || hasInput) && liveVariance > 0" class="font-semibold text-red-600" x-text="`${liveVarianceDisplay} {{ $inventoryUnit }}`"></span>
                <span x-show="(wasCounted || hasInput) && liveVariance < 0" class="font-semibold text-green-600" x-text="`${liveVarianceDisplay} {{ $inventoryUnit }}`"></span>
                <span x-show="!(wasCounted || hasInput) || liveVariance === 0" class="font-semibold text-gray-600">0.00 {{ $inventoryUnit }}</span>
            </span>
        </div>
    </div>

    {{-- Notifikasi: fisik > stok sistem --}}
    <div x-show="isOverSystemStock" x-cloak
         class="mt-2 bg-blue-50 border border-blue-200 rounded-xl p-3">
        <div class="flex items-start gap-2">
            <i class="ti ti-info-circle text-blue-500 flex-shrink-0 mt-0.5 text-sm" aria-hidden="true"></i>
            <div>
                <p class="text-xs font-semibold text-blue-800">Stok fisik melebihi stok sistem</p>
                <p class="text-xs text-blue-600 mt-0.5">Kemungkinan ada penerimaan barang yang belum dicatat. Silakan cek menu Penerimaan Barang.</p>
            </div>
        </div>
    </div>

    {{-- Notifikasi: stok 0 tapi ada fisik --}}
    <div x-show="isSuspiciousWhenZero" x-cloak
         class="mt-2 bg-orange-50 border border-orange-200 rounded-xl p-3">
        <div class="flex items-start gap-2">
            <i class="ti ti-alert-triangle text-orange-500 flex-shrink-0 mt-0.5 text-sm" aria-hidden="true"></i>
            <div>
                <p class="text-xs font-semibold text-orange-800">Ada stok fisik padahal stok sistem = 0</p>
                <p class="text-xs text-orange-600 mt-0.5">Pastikan ada penerimaan barang yang sudah dicatat, atau ini memang stok awal yang belum diinput.</p>
            </div>
        </div>
    </div>

    <div x-show="suggestion" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
        <div class="mb-2 flex items-center justify-between gap-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3a6 6 0 00-3.6 10.8c.75.56 1.263 1.315 1.263 2.2h4.674c0-.885.513-1.64 1.263-2.2A6 6 0 0012 3z"/>
                </svg>
                Saran Order
            </span>
            <span class="text-xs text-amber-600"
                  x-text="suggestion && suggestion.days_remaining !== null ? suggestion.days_remaining.toFixed(1) + ' hari lagi habis' : 'Belum ada pola pemakaian'"></span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
                <span class="text-amber-600">Rata-rata/hari</span>
                <p class="font-semibold text-amber-900"
                   x-text="suggestion ? formatQty(suggestion.avg_daily_usage) + ' ' + suggestion.unit_abbreviation : ''"></p>
            </div>
            <div>
                <span class="text-amber-600">Rekomendasi order</span>
                <p class="text-sm font-bold text-amber-900"
                   x-text="suggestion ? formatQty(suggestion.recommended_order) + ' ' + suggestion.unit_abbreviation : ''"></p>
            </div>
        </div>

        <template x-if="suggestion && suggestion.upcoming_events && suggestion.upcoming_events.length > 0">
            <div class="mt-2 border-t border-amber-200 pt-2">
                <p class="flex items-center gap-1.5 text-xs text-amber-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z"/>
                    </svg>
                    <span x-text="suggestion.upcoming_events[0].name"></span>
                    <span x-text="formatDemandChange(suggestion.upcoming_events[0].demand_change_pct)"></span>
                </p>
            </div>
        </template>

        <p x-show="suggestion && suggestion.is_critical" class="mt-2 text-xs font-bold text-red-600">
            KRITIS - stok di bawah minimum.
        </p>
    </div>

    <p x-show="saved" x-transition class="mt-3 text-xs font-semibold text-primary-700">Tersimpan</p>
</div>
