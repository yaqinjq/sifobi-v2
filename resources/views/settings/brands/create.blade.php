@extends('layouts.app')

@section('title', 'Tambah Brand')

@section('content')
<x-sf.page-header title="Tambah Brand" subtitle="Brand bisnis dalam tenant" back="{{ route('settings.brands.index') }}" />

@include('settings.brands._form', [
    'action' => route('settings.brands.store'),
])
@endsection
