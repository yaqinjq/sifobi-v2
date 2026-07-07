@props(['title', 'subtitle' => null, 'back' => null])

<div class="sticky top-0 z-30 bg-primary-800 safe-top">
    <div class="flex items-center gap-3 px-4 py-3">
        @if($back)
            <a href="{{ $back }}"
               class="flex items-center justify-center w-9 h-9 rounded-xl
                      bg-primary-700 hover:bg-primary-600 text-white transition-colors shrink-0"
               aria-label="Kembali">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        @else
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-700 shrink-0">
                <span class="font-heading font-bold text-white text-sm">SF</span>
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <h1 class="font-heading font-semibold text-white text-base truncate">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-primary-300 text-xs truncate mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-1.5 shrink-0">{{ $actions }}</div>
        @endisset
    </div>
</div>
