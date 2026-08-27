@php
    $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
    $isCustomized = $item->track_stock || ! str_starts_with($item->category?->name ?? '', 'Wipro —');
@endphp
<a href="{{ route('master-data.wipro-items.edit', $item) }}" class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 hover:bg-gray-50 transition-colors">
    <div class="w-12 h-12 rounded-xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
        @if($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
        @else
            <i class="ti ti-photo text-gray-300 text-lg" aria-hidden="true"></i>
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-gray-900 truncate">{{ $item->name }}</p>
        <p class="text-xs text-gray-500">{{ $item->canonical_sku }} &middot; {{ $item->category?->name ?? '-' }}</p>
        @if($isCustomized)
            <span class="badge-blue text-[10px] mt-1 inline-block">Disesuaikan manual</span>
        @endif
    </div>
    <span class="{{ $item->is_active ? 'badge-active' : 'badge-inactive' }} shrink-0">
        {{ $item->is_active ? 'Aktif' : 'Non-Aktif' }}
    </span>
    <i class="ti ti-chevron-right text-gray-300 shrink-0" aria-hidden="true"></i>
</a>
