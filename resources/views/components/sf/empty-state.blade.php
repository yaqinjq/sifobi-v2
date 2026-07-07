@props(['icon' => '📦', 'title', 'description' => null, 'action' => null, 'actionLabel' => null])

<div class="flex flex-col items-center justify-center py-16 px-6 text-center">
    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-3xl mb-4">
        {{ $icon }}
    </div>
    <h3 class="font-heading font-semibold text-gray-900 text-base">{{ $title }}</h3>
    @if($description)
        <p class="text-sm text-gray-500 mt-2 max-w-xs leading-relaxed">{{ $description }}</p>
    @endif
    @if($action && $actionLabel)
        <a href="{{ $action }}" class="sf-btn-primary mt-6">{{ $actionLabel }}</a>
    @endif
    @isset($slot)
        @unless($slot->isEmpty())
            <div class="mt-6">{{ $slot }}</div>
        @endunless
    @endisset
</div>
