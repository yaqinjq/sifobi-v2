@props(['options' => [25, 50, 100, 1000], 'current' => 25])

<label class="flex items-center gap-1.5 text-xs text-gray-500">
    Tampilkan
    <select onchange="var p=window.location.search.slice(1).split('&').filter(Boolean).reduce(function(a,c){var kv=c.split('=');a[decodeURIComponent(kv[0])]=kv[1]!==undefined?decodeURIComponent(kv[1].replace(/\+/g,'%20')):'';return a;},{});p.per_page=this.value;delete p.page;var qs=Object.keys(p).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(p[k]);}).join('&');window.location.href=window.location.pathname+(qs?'?'+qs:'');"
            autocomplete="off"
            class="sf-input py-1.5 text-xs w-auto min-h-9">
        @foreach($options as $option)
            <option value="{{ $option }}" @selected((int) $current === (int) $option)>{{ $option >= 1000 ? 'Semua' : $option }}</option>
        @endforeach
    </select>
    per halaman
</label>
