@props([
    'title' => null,
    'value' => null,
    'meta'  => null,
])

<section {{ $attributes->merge(['class' => 'sf-card']) }}>
    @if($title || $value !== null)
        <div class="p-4 {{ $slot->isEmpty() ? '' : 'border-b border-gray-50' }}">
            @if($title)
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $title }}</p>
            @endif
            @if($value !== null)
                <p class="mt-1 text-2xl font-bold font-heading text-gray-900">{{ $value }}</p>
            @endif
            @if($meta)
                <p class="mt-0.5 text-sm text-gray-500">{{ $meta }}</p>
            @endif
        </div>
    @endif

    @unless($slot->isEmpty())
        <div class="p-4">{{ $slot }}</div>
    @endunless
</section>
