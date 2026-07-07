@extends('layouts.app')

@section('title', 'Edit Open Stock')
@section('hide-bottom-nav', 'true')

@section('topbar')
<x-sf.page-header
    title="Edit Open Stock"
    subtitle="{{ $openStock->item?->name }}"
    back="{{ route('operations.open-stocks.show', $openStock) }}"
/>
@endsection

@section('content')
    <form method="POST" action="{{ route('operations.open-stocks.update', $openStock) }}">
        @csrf
        @method('PUT')
        @include('operations.open-stocks._form')
    </form>
@endsection
