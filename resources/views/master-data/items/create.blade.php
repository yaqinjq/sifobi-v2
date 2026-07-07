@extends('layouts.app')

@section('title', 'Tambah Item')

@section('content')
<x-sf.page-header
    title="Tambah Item"
    subtitle="Master bahan baku & produk"
    back="{{ route('master-data.items.index') }}"
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
    'action' => route('master-data.items.store'),
])
@endsection
