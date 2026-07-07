@extends('layouts.app')

@section('title', 'Tambah User')

@section('content')
<x-sf.page-header title="Tambah User" subtitle="Buat akun pengguna baru" back="{{ route('settings.users.index') }}" />

@include('settings.users._form', [
    'action' => route('settings.users.store'),
    'method' => 'POST',
    'submitLabel' => 'Simpan User',
])
@endsection
