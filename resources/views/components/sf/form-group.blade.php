@props(['label', 'for', 'required' => false, 'hint' => null])

<div class="space-y-1.5">
    <label for="{{ $for }}" class="sf-label">
        {{ $label }}
        @if($required)
            <span class="text-red-500 normal-case font-normal ml-0.5">*</span>
        @endif
    </label>

    {{ $slot }}

    @error($for)
        <p class="text-xs text-red-500 flex items-center gap-1 mt-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
        </p>
    @else
        @if($hint)
            <p class="text-xs text-gray-400">{{ $hint }}</p>
        @endif
    @enderror
</div>
