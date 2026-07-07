@extends('layouts.app')

@section('title', 'Edit Item')

@section('content')
<x-sf.page-header
    title="Edit Item"
    subtitle="{{ $item->canonical_sku }}"
    back="{{ route('master-data.items.show', $item) }}"
/>

@include('master-data.items._form', [
    'item' => $item,
    'units' => $units,
    'departments' => $departments,
    'outlets' => $outlets,
    'brands' => $brands,
    'jenises' => $jenises,
    'itemTypes' => $itemTypes,
    'opnameFrequencies' => $opnameFrequencies,
    'action' => route('master-data.items.update', $item),
])
@endsection
