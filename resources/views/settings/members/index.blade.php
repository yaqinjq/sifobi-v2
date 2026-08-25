@extends('layouts.app')

@section('title', 'Member & Loyalty')

@section('content')
<x-sf.page-header
    title="Member & Loyalty"
    subtitle="Daftar member dan saldo poin"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau HP..." class="sf-input text-sm flex-1">
        <button type="submit" class="sf-btn-secondary min-h-11 px-4">Cari</button>
    </form>

    <x-sf.card title="Daftar Member">
        @if($members->isEmpty())
            <p class="text-sm text-gray-400 py-8 text-center">Belum ada member terdaftar.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($members as $member)
                    <a href="{{ route('settings.members.show', $member) }}" class="flex items-center justify-between gap-3 px-3 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $member->name }}</p>
                            <p class="text-xs text-gray-500">{{ $member->phone }}</p>
                        </div>
                        <span class="text-sm font-semibold text-primary-700 shrink-0">{{ number_format((float) $member->points_balance, 0, ',', '.') }} poin</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-3">{{ $members->links() }}</div>
        @endif
    </x-sf.card>
</div>
@endsection
