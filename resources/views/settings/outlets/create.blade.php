@extends('layouts.app')

@section('title', 'Tambah Outlet')

@section('content')
<x-sf.page-header title="Tambah Outlet" subtitle="Outlet operasional per brand" back="{{ route('settings.outlets.index') }}" />

@include('settings.outlets._form', [
    'action' => route('settings.outlets.store'),
])
@endsection
