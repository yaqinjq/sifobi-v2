@extends('layouts.app')

@section('title', 'Tambah Satuan')

@section('content')
<x-sf.page-header
    title="Tambah Satuan"
    subtitle="Unit dasar untuk item inventory"
    back="{{ route('master-data.units.index') }}"
/>

@include('master-data.units._form', [
    'unit' => $unit,
    'action' => route('master-data.units.store'),
])
@endsection
