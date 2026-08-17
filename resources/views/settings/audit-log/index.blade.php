@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('content')
<x-sf.page-header title="Log Aktivitas" subtitle="Riwayat perubahan data, login, dan role/permission" back="{{ route('settings.index') }}" />

<div class="sticky top-[4.25rem] z-20 bg-white border-b border-gray-100 px-4 py-3 lg:top-0">
    <form method="GET" action="{{ route('settings.audit-log.index') }}" class="flex flex-col md:flex-row gap-2 max-w-6xl mx-auto">
        <input type="text"
               name="q"
               value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari deskripsi aktivitas..."
               class="sf-input text-base md:flex-1"
               autocomplete="off">

        <select name="log_name" class="sf-input text-base md:w-48">
            <option value="">Semua Modul</option>
            @foreach($logNames as $value => $label)
                <option value="{{ $value }}" @selected(($filters['log_name'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="causer_id" class="sf-input text-base md:w-48">
            <option value="">Semua User</option>
            @foreach($actors as $actor)
                <option value="{{ $actor->id }}" @selected((string) ($filters['causer_id'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="sf-input text-base md:w-40">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="sf-input text-base md:w-40">

        <div class="grid grid-cols-2 gap-2 md:flex">
            <button type="submit" class="sf-btn-secondary min-h-11">Filter</button>
            <a href="{{ route('settings.audit-log.index') }}" class="sf-btn-secondary min-h-11 text-center">Reset</a>
        </div>
    </form>
</div>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    <x-sf.card title="Riwayat Aktivitas" :padding="false">
        @if($entries->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Belum ada aktivitas yang tercatat.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($entries as $entry)
                    @php
                        $badgeClass = match ($entry->log_name) {
                            'auth' => 'badge-active',
                            'user' => 'badge-blue',
                            'procurement', 'receiving' => 'badge-pending',
                            'production' => 'badge-purple',
                            'operations', 'stock' => 'badge-draft',
                            default => 'badge-draft',
                        };
                    @endphp
                    <a href="{{ route('settings.audit-log.show', $entry) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="{{ $badgeClass }} text-xs">{{ $logNames[$entry->log_name] ?? ($entry->log_name ?? '-') }}</span>
                                <span class="text-xs text-gray-400">{{ $entry->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <p class="text-sm text-gray-800 mt-1">{{ $entry->description }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                oleh {{ $entry->causer?->name ?? 'Sistem' }}
                                @if($entry->subject)
                                    &middot; {{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}
                                @endif
                            </p>
                        </div>
                        <i class="ti ti-chevron-right text-gray-300 text-base shrink-0 mt-1" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $entries->links() }}
        </div>
    </x-sf.card>
</div>
@endsection
