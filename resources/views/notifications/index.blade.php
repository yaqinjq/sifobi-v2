@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<x-sf.page-header title="Notifikasi" subtitle="Semua notifikasi untuk Anda" back="{{ route('dashboard') }}">
    <x-slot:actions>
        @if($notifications->contains(fn ($n) => $n->read_at === null))
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="text-primary-200 hover:text-white text-xs font-medium">Tandai semua dibaca</button>
            </form>
        @endif
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    <x-sf.card :padding="false">
        @if($notifications->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada notifikasi.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($notifications as $notification)
                    @php
                        $unread = $notification->read_at === null;
                        $badgeClass = match ($notification->data['module'] ?? null) {
                            'procurement' => 'badge-pending',
                            'receiving' => 'badge-pending',
                            'production' => 'badge-purple',
                            'operations', 'stock' => 'badge-draft',
                            default => 'badge-draft',
                        };
                    @endphp
                    <a href="{{ route('notifications.open', $notification->id) }}"
                       class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors {{ $unread ? 'bg-primary-50' : '' }}">
                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $unread ? 'bg-primary-600' : 'bg-transparent' }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-900">{{ $notification->data['title'] ?? '-' }}</p>
                                <span class="{{ $badgeClass }} text-xs">{{ ucfirst($notification->data['module'] ?? '-') }}</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-0.5">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $notifications->links() }}
        </div>
    </x-sf.card>
</div>
@endsection
