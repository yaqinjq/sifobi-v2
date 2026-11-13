@props(['values' => [], 'label' => null])

@php
    $max = max(array_merge($values, [0])) ?: 1;
@endphp

<div class="flex items-end gap-1 h-10" role="img" aria-label="{{ $label ?? 'Tren 7 hari' }}">
    @foreach($values as $v)
        @php $pct = $v > 0 ? max(8, (int) round($v / $max * 100)) : 2; @endphp
        <div class="flex-1 rounded-t bg-primary-200" style="height: {{ $pct }}%"
             title="{{ number_format($v, 0, ',', '.') }}"></div>
    @endforeach
</div>
