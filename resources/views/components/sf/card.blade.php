@props(['title' => null, 'subtitle' => null, 'padding' => true])

<div class="sf-card">
    @if($title)
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $title }}</h3>
                @if($subtitle)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $padding ? 'p-4' : '' }}">{{ $slot }}</div>
</div>
