@extends('layouts.app')

@section('title', 'Kitchen Display')

@section('content')
<x-sf.page-header
    title="Kitchen Display"
    subtitle="Order yang perlu diproses"
    back="{{ route('pos.orders.index', ['outlet_id' => $outletId]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    <form method="GET" class="flex items-center gap-2">
        <select name="outlet_id" class="sf-input text-sm" onchange="this.form.submit()">
            @foreach($outlets as $o)
                <option value="{{ $o->id }}" @selected($o->id == $outletId)>{{ $o->name }}</option>
            @endforeach
        </select>
    </form>

    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div id="kds-list"
         x-data
         x-init="setInterval(() => {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => { $el.innerHTML = html; });
         }, 8000)">
        @include('pos.kitchen._list')
    </div>
</div>
@endsection
