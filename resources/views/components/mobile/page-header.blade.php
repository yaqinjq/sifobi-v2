@props([
    'title',
    'subtitle' => null,
])

<header class="mb-5 px-4 pt-4">
    <div class="flex min-h-11 items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold font-heading text-gray-900 leading-tight">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($action)
            <div class="shrink-0 pt-0.5">{{ $action }}</div>
        @endisset
    </div>
</header>
