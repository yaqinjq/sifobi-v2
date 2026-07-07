@extends('layouts.app')

@section('title', 'Edit Penerimaan')

@section('content')
<x-sf.page-header title="Edit Penerimaan" subtitle="{{ $receipt->code }}" back="{{ route('receiving.goods-receipts.show', $receipt) }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">
    @include('receiving.goods-receipts._form', [
        'formAction' => route('receiving.goods-receipts.update', $receipt),
        'formMethod' => 'PUT',
    ])
</div>
@endsection
