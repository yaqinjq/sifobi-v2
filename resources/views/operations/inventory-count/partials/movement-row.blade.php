@php
    $item = $row['item'];
    $result = $row['result'];
    $days = $result['days'];
    // Kalau belum ada saldo awal sama sekali (days kosong), tetap render 7
    // sel kosong supaya staf bisa isi saldo awal dulu -- bukan baris hilang.
    if (empty($days)) {
        $days = collect(range(0, 6))->map(fn ($i) => [
            'date' => $weekStart->copy()->addDays($i)->toDateString(),
            'qty_in' => null,
            'qty_out' => null,
            'prior_balance' => null,
            'balance' => null,
            'error' => $result['error'],
        ])->all();
    }
@endphp
<tr x-show="isVisible()"
    x-data="movementRow({
        itemId: {{ $item->id }},
        itemName: @js($item->name),
        category: @js($row['subcategory']),
        autosaveUrl: @js(route('operations.inventory-count.movements.autosave', $item)),
        openingBalanceUrl: @js(route('operations.inventory-count.movements.opening-balance', $item)),
        weekStartDate: @js($weekStart->toDateString()),
        openingBalance: @js($result['base']),
        days: @js($days),
        error: @js($result['error']),
    })"
    class="border-t border-gray-100">
    <td class="sticky left-0 z-10 bg-white px-3 py-2 align-top w-[180px]">
        <p class="font-medium text-gray-900">{{ $item->name }}</p>
        <p class="text-[11px] text-gray-400">{{ $row['unit'] }}</p>
        <p x-show="error" x-cloak class="text-[11px] font-semibold text-red-600 mt-1" x-text="error"></p>
    </td>
    <td class="sticky left-[180px] z-10 bg-white px-2 py-2 align-top">
        <input type="text" inputmode="decimal" x-model="openingBalanceInput"
               @change="saveOpeningBalance()"
               placeholder="Isi saldo awal"
               class="sf-input text-center min-h-10 text-xs w-full">
        <p x-show="savingOpening" class="text-[10px] text-gray-400 text-center mt-0.5">Menyimpan...</p>
    </td>
    @for($i = 0; $i < 7; $i++)
        <td class="px-1 py-1 border-l border-gray-100 align-top">
            <input type="text" inputmode="decimal" x-model="days[{{ $i }}].qty_in"
                   @change="saveDay({{ $i }})"
                   placeholder="-"
                   class="sf-input text-center min-h-9 text-xs w-full px-1">
        </td>
        <td class="px-1 py-1 align-top">
            <input type="text" inputmode="decimal" x-model="days[{{ $i }}].qty_out"
                   @change="saveDay({{ $i }})"
                   placeholder="-"
                   class="sf-input text-center min-h-9 text-xs w-full px-1">
        </td>
        <td class="px-1 py-1 align-top text-center">
            <span x-show="savingDay === {{ $i }}" class="text-[10px] text-gray-400">...</span>
            <template x-if="savingDay !== {{ $i }}">
                <span class="text-xs font-semibold" :class="days[{{ $i }}].balance === null ? 'text-red-500' : 'text-gray-700'"
                      x-text="days[{{ $i }}].balance === null ? (days[{{ $i }}].error ? '!' : '-') : formatBalance(days[{{ $i }}].balance)"></span>
            </template>
        </td>
    @endfor
</tr>
