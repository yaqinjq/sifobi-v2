@props([
    'icon',
    'label',
    'href' => null,
    'method' => null,
    'color' => 'gray',
    'size' => 'md',
    'confirm' => null,
])

@php
    $colors = [
        'gray' => 'bg-gray-100 hover:bg-gray-200 text-gray-600',
        'blue' => 'bg-blue-50 hover:bg-blue-100 text-blue-600',
        'red' => 'bg-red-50 hover:bg-red-100 text-red-600',
        'green' => 'bg-green-50 hover:bg-green-100 text-green-700',
        'amber' => 'bg-amber-50 hover:bg-amber-100 text-amber-700',
        'purple' => 'bg-purple-50 hover:bg-purple-100 text-purple-700',
    ];
    $sizes = [
        'sm' => 'min-h-11 min-w-11 px-1.5 py-1.5',
        'md' => 'min-h-11 min-w-11 px-2 py-1.5',
        'lg' => 'min-h-12 min-w-12 px-2.5 py-2',
    ];
    $iconSizes = [
        'sm' => 'h-4 w-4',
        'md' => 'h-4.5 w-4.5',
        'lg' => 'h-5 w-5',
    ];
    $icons = [
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
        'delete' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
        'view' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
        'approve' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'reject' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'post' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>',
        'void' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
        'download' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>',
        'upload' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>',
        'toggle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>',
        'history' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'config' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ];

    $colorClass = $colors[$color] ?? $colors['gray'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $iconSizeClass = $iconSizes[$size] ?? $iconSizes['md'];
    $svgPath = $icons[$icon] ?? $icons['view'];
    $buttonClass = "inline-flex flex-col items-center justify-center gap-0.5 rounded-xl {$colorClass} {$sizeClass} shrink-0 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-1 disabled:pointer-events-none disabled:opacity-50";
@endphp

@if($href && ! $method)
    <a href="{{ $href }}"
       title="{{ $label }}"
       aria-label="{{ $label }}"
       {{ $attributes->class([$buttonClass]) }}>
        <svg class="{{ $iconSizeClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            {!! $svgPath !!}
        </svg>
        <span class="max-w-16 text-center text-[10px] leading-tight md:sr-only">{{ $label }}</span>
    </a>
@elseif($href && $method)
    <form method="POST"
          action="{{ $href }}"
          @if($confirm) data-confirm="{{ $confirm }}" onsubmit="return window.confirm(this.dataset.confirm)" @endif
          class="inline-flex">
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method(strtoupper($method))
        @endif
        <button type="submit"
                title="{{ $label }}"
                aria-label="{{ $label }}"
                {{ $attributes->class([$buttonClass]) }}>
            <svg class="{{ $iconSizeClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                {!! $svgPath !!}
            </svg>
            <span class="max-w-16 text-center text-[10px] leading-tight md:sr-only">{{ $label }}</span>
        </button>
    </form>
@else
    <button type="{{ $attributes->get('type', 'button') }}"
            title="{{ $label }}"
            aria-label="{{ $label }}"
            {{ $attributes->except('type')->class([$buttonClass]) }}>
        <svg class="{{ $iconSizeClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            {!! $svgPath !!}
        </svg>
        <span class="max-w-16 text-center text-[10px] leading-tight md:sr-only">{{ $label }}</span>
    </button>
@endif
