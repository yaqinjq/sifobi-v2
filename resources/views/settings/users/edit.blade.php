@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<x-sf.page-header title="Edit User" subtitle="{{ $user->name }}" back="{{ route('settings.users.index') }}" />

@include('settings.users._form', [
    'action' => route('settings.users.update', $user),
    'method' => 'PUT',
    'submitLabel' => 'Simpan Perubahan',
])
@endsection
