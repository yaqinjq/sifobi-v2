@extends('layouts.app')

@section('title', 'Edit Outlet')

@section('content')
<x-sf.page-header title="Edit Outlet" subtitle="{{ $outlet->name }}" back="{{ route('settings.outlets.index') }}" />

@include('settings.outlets._form', [
    'action' => route('settings.outlets.update', $outlet),
])
@endsection
