@props([
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => 'Cari...',
    'allLabel' => null,
])

<div
    x-data="{
        open: false,
        query: '',
        options: @js(collect($options)->values()),
        selectedValue: @js((string) ($selected ?? '')),
        get selectedLabel() {
            if (this.selectedValue === '') return @js($allLabel ?? $placeholder);
            const found = this.options.find(o => String(o.value) === this.selectedValue);
            return found ? found.label : @js($allLabel ?? $placeholder);
        },
        get filtered() {
            if (this.query === '') return this.options;
            const q = this.query.toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        toggle() {
            this.open = !this.open;
            this.query = '';
            if (this.open) this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        select(opt) {
            this.selectedValue = opt ? String(opt.value) : '';
            this.open = false;
            this.query = '';
            this.$el.closest('form').submit();
        }
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" :value="selectedValue">

    <button type="button" @click="toggle()"
            class="sf-input text-sm w-auto min-h-11 flex items-center justify-between gap-2 text-left"
            :class="selectedValue !== '' ? 'text-gray-900' : 'text-gray-500'">
        <span x-text="selectedLabel" class="truncate max-w-[10rem]"></span>
        <i class="ti ti-chevron-down text-sm shrink-0" aria-hidden="true"></i>
    </button>

    <div x-show="open" x-cloak
         class="absolute left-0 z-40 mt-1 w-64 max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg">
        <div class="p-2 border-b border-gray-100 sticky top-0 bg-white">
            <div class="relative">
                <i class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <input type="text" x-ref="searchInput" x-model="query"
                       placeholder="{{ $placeholder }}"
                       class="sf-input text-sm min-h-9 w-full pl-8">
            </div>
        </div>
        <ul class="py-1">
            @if($allLabel)
                <li @click="select(null)"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
                    :class="selectedValue === '' ? 'font-semibold text-primary-700' : 'text-gray-700'">
                    {{ $allLabel }}
                </li>
            @endif
            <template x-for="opt in filtered" :key="opt.value">
                <li @click="select(opt)"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
                    :class="opt.indent ? 'pl-6 text-gray-600' : 'text-gray-900 font-medium'"
                    x-text="(opt.indent ? '— ' : '') + opt.label"></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">Tidak ditemukan</li>
        </ul>
    </div>
</div>
