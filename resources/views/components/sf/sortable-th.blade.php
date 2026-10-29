@props(['column', 'sort', 'direction', 'align' => 'left'])

<th {{ $attributes->merge(['class' => ($align === 'right' ? 'text-right' : 'text-left').' px-4 py-3']) }}>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc']) }}"
       class="group flex items-center gap-1 whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-primary-700 {{ $align === 'right' ? 'justify-end' : '' }}">
        {{ $slot }}
        @if($sort === $column)
            @if($direction === 'asc')
                <i class="ti ti-sort-ascending text-sm text-primary-600" aria-hidden="true"></i>
            @else
                <i class="ti ti-sort-descending text-sm text-primary-600" aria-hidden="true"></i>
            @endif
        @else
            <i class="ti ti-arrows-sort text-sm text-gray-300 group-hover:text-gray-400" aria-hidden="true"></i>
        @endif
    </a>
</th>
