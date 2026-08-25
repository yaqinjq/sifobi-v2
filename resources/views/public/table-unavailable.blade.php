@extends('layouts.public')

@section('title', 'Meja Tidak Tersedia')

@section('content')
<div class="px-4 py-16 max-w-lg mx-auto w-full text-center">
    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-3xl mb-4 mx-auto">🪑</div>
    <h1 class="font-heading font-semibold text-gray-900 text-lg">Meja {{ $table->code }} Belum Bisa Dipesan</h1>
    <p class="text-sm text-gray-500 mt-2">Silakan hubungi staff untuk bantuan memesan.</p>
</div>
@endsection
