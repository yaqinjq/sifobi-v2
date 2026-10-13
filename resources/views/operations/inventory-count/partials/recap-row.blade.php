@php
    $locationLabels = [
        'GUDANG' => 'Gudang',
        'BAR_AREA' => 'Bar Area',
        'KITCHEN_AREA' => 'Kitchen Area',
        'GENERIC' => '-',
    ];
    $locationLabel = $locationLabels[$row['location']] ?? $row['location'];
@endphp
<tr x-data="{ itemName: @js($row['item']->name ?? ''), matchesSearch() { const q = Alpine.store('recap').search.trim().toLowerCase(); return q === '' || this.itemName.toLowerCase().includes(q); } }"
    x-show="matchesSearch()"
    class="border-t border-gray-100">
    <td class="sticky left-0 z-10 bg-white px-3 py-2 align-top w-[180px]">
        <p class="font-medium text-gray-900">{{ $row['item']->name ?? '-' }}</p>
    </td>
    <td class="sticky left-[180px] z-10 bg-white px-2 py-2 align-top text-gray-600">{{ $locationLabel }}</td>
    @foreach($row['days'] as $day)
        <td class="px-2 py-2 border-l border-gray-100 align-top text-center">
            @if($day['counted'])
                <p class="font-semibold text-gray-900">{{ $day['qty_whole'] }} <span class="font-normal text-gray-400">{{ $day['whole_unit'] }}</span></p>
                @if($day['qty_loose'] !== null)
                    <p class="text-gray-600">{{ $day['qty_loose'] }} <span class="text-gray-400">{{ $day['loose_unit'] }}</span></p>
                @endif
                @if(! $day['config_valid'])
                    <p class="text-[10px] text-amber-600 mt-0.5"><i class="ti ti-alert-triangle" aria-hidden="true"></i> satuan belum valid</p>
                @endif
            @else
                <p class="text-gray-300 italic">Belum dihitung</p>
            @endif
        </td>
    @endforeach
</tr>
