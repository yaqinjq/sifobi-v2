@extends('layouts.app')

@section('title', 'Edit Brand')

@section('content')
<x-sf.page-header title="Edit Brand" subtitle="{{ $brand->name }}" back="{{ route('settings.brands.index') }}" />

@include('settings.brands._form', [
    'action' => route('settings.brands.update', $brand),
])
@endsection
