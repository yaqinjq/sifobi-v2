@extends('layouts.app')

@section('title', 'Edit Satuan')

@section('content')
<x-sf.page-header
    title="Edit Satuan"
    subtitle="{{ $unit->code }}"
    back="{{ route('master-data.units.index') }}"
/>

@include('master-data.units._form', [
    'unit' => $unit,
    'action' => route('master-data.units.update', $unit),
])
@endsection
