@props(['options' => [25, 50, 100, 1000], 'current' => 25])

<label class="flex items-center gap-1.5 text-xs text-gray-500">
    Tampilkan
    <select onchange="const u = new URL(window.location); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); window.location.href = u.toString();"
            class="sf-input py-1.5 text-xs w-auto min-h-9">
        @foreach($options as $option)
            <option value="{{ $option }}" @selected((int) $current === (int) $option)>{{ $option >= 1000 ? 'Semua' : $option }}</option>
        @endforeach
    </select>
    per halaman
</label>
