@extends('layouts.app')

@section('title', 'Penerimaan Barang Baru')

@section('content')
<x-sf.page-header title="Penerimaan Barang Baru" subtitle="Pilih sumber dan input barang diterima" back="{{ route('receiving.goods-receipts.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">
    @if(! $source)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($sources as $value => $label)
                <x-sf.card>
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">Sumber</p>
                        <h2 class="font-heading text-lg font-bold text-gray-900">{{ $label }}</h2>
                        <p class="text-sm text-gray-500 min-h-10">
                            @if($value === 'OCIA_PO')
                                Pilih PO OCIA dan konfirmasi qty fisik yang diterima.
                            @elseif($value === 'WIP_CENTRAL_KITCHEN')
                                Terima premix/WIP dari Central Kitchen.
                            @elseif($value === 'PURCHASING_DRYGOOD')
                                Input drygood dari alur purchasing.
                            @else
                                Input supplier luar, dokumen manual, atau foto invoice.
                            @endif
                        </p>
                        <a href="{{ route('receiving.goods-receipts.create', ['source' => $value]) }}" class="sf-btn-primary min-h-11 w-full">Pilih</a>
                    </div>
                </x-sf.card>
            @endforeach
        </div>
    @else
        @include('receiving.goods-receipts._form', [
            'formAction' => route('receiving.goods-receipts.store'),
            'formMethod' => 'POST',
        ])
    @endif
</div>
@endsection
