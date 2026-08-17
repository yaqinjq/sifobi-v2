@props(['name', 'options', 'selected' => [], 'placeholder' => 'Cari...'])

<div x-data="{
        open: false,
        search: '',
        selected: @js(array_values($selected)),
        options: @js($options),
        get filtered() {
            const q = this.search.toLowerCase();
            return this.options.filter((o) => o.label.toLowerCase().includes(q));
        },
        toggle(value) {
            const idx = this.selected.indexOf(value);
            if (idx === -1) { this.selected.push(value); } else { this.selected.splice(idx, 1); }
        },
        label(value) {
            const found = this.options.find((o) => o.value === value);
            return found ? found.label : value;
        },
     }"
     class="relative">
    <template x-for="value in selected" :key="value">
        <input type="hidden" name="{{ $name }}" :value="value">
    </template>

    <div class="flex flex-wrap gap-1.5 mb-1.5" x-show="selected.length > 0" x-cloak>
        <template x-for="value in selected" :key="value">
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 text-primary-700 text-xs font-medium pl-2.5 pr-1.5 py-1">
                <span x-text="label(value)"></span>
                <button type="button" @click="toggle(value)" class="text-primary-400 hover:text-primary-700 leading-none text-sm">&times;</button>
            </span>
        </template>
    </div>

    <input type="text" x-model="search" @focus="open = true" @click.outside="open = false"
           class="sf-input text-base min-h-11" placeholder="{{ $placeholder }}" autocomplete="off">

    <div x-show="open" x-cloak
         class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg py-1">
        <template x-for="option in filtered" :key="option.value">
            <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" :checked="selected.includes(option.value)" @change="toggle(option.value)"
                       class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                <span x-text="option.label"></span>
            </label>
        </template>
        <p x-show="filtered.length === 0" class="px-3 py-2 text-xs text-gray-400">Tidak ada hasil</p>
    </div>
</div>
