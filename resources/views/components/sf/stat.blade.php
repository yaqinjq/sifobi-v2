@props(['label', 'value', 'icon' => null, 'trend' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    {{ $href ? "href=$href" : '' }}
    class="sf-stat-card {{ $href ? 'hover:shadow-card-hover transition-shadow' : '' }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="sf-stat-label">{{ $label }}</p>
            <p class="sf-stat-value mt-1">{{ $value }}</p>
            @if($trend)
                <p class="text-xs mt-1 {{ str_starts_with((string) $trend, '+') ? 'text-green-600' : 'text-red-500' }}">
                    {{ $trend }}
                </p>
            @endif
        </div>
        @if($icon)
            <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center text-xl shrink-0">
                {{ $icon }}
            </div>
        @endif
    </div>
</{{ $tag }}>
