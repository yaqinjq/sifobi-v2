@props([
    'selectedVar' => 'selected',
    'route',
    'confirmMessage',
    'buttonLabel',
    'buttonIcon' => 'ti-check',
])

<div x-show="{{ $selectedVar }}.length > 0" x-cloak
     class="fixed inset-x-0 bottom-16 z-30 border-t border-gray-200 bg-white px-4 py-3 shadow-lg md:bottom-0 md:px-6">
    <form method="POST" action="{{ $route }}"
          class="mx-auto flex max-w-7xl items-center justify-between gap-3"
          @submit="if (! confirm(`{{ $confirmMessage }}`.replace('__COUNT__', {{ $selectedVar }}.length))) { $event.preventDefault(); }">
        @csrf
        {{ $slot ?? '' }}
        <template x-for="id in {{ $selectedVar }}" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
        <p class="text-sm text-gray-600">
            <span class="font-semibold text-gray-900" x-text="{{ $selectedVar }}.length"></span> terpilih
        </p>
        <button type="submit" class="sf-btn-primary min-h-11 px-4">
            <i class="ti {{ $buttonIcon }} text-base" aria-hidden="true"></i>
            {{ $buttonLabel }}
        </button>
    </form>
</div>
