@extends('layouts.app')

@section('title', 'Detail Aktivitas')

@section('content')
<x-sf.page-header title="Detail Aktivitas" subtitle="{{ $entry->created_at->format('d M Y H:i') }}" back="{{ route('settings.audit-log.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    <x-sf.card>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs text-gray-400">Aktivitas</p>
                <p class="text-gray-800 font-medium">{{ $entry->description }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Modul</p>
                <p class="text-gray-800 font-medium">{{ $entry->log_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Dilakukan Oleh</p>
                <p class="text-gray-800 font-medium">{{ $entry->causer?->name ?? 'Sistem' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Objek</p>
                <p class="text-gray-800 font-medium">
                    @if($entry->subject)
                        {{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}
                    @else
                        -
                    @endif
                </p>
            </div>
        </div>
    </x-sf.card>

    @php
        $old = collect($entry->attribute_changes['old'] ?? []);
        $new = collect($entry->attribute_changes['attributes'] ?? []);
        $keys = $old->keys()->merge($new->keys())->unique();
    @endphp

    @if($keys->isNotEmpty())
        <x-sf.card title="Perubahan Data">
            <div class="divide-y divide-gray-50">
                @foreach($keys as $key)
                    <div class="py-2.5 grid grid-cols-1 md:grid-cols-3 gap-1 text-sm">
                        <p class="font-medium text-gray-700">{{ \App\Support\AuditAttributeLabels::label($key) }}</p>
                        <p class="text-gray-400 line-through">{{ $old->get($key) ?? '-' }}</p>
                        <p class="text-gray-900 font-medium">{{ $new->get($key) ?? '-' }}</p>
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    @if($entry->properties->isNotEmpty())
        <x-sf.card title="Detail Tambahan">
            <div class="divide-y divide-gray-50">
                @foreach($entry->properties as $key => $value)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-500">{{ \App\Support\AuditAttributeLabels::label((string) $key) }}</span>
                        <span class="text-gray-800 font-medium text-right">{{ is_array($value) ? implode(', ', $value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif
</div>
@endsection
