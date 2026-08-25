@extends('layouts.app')

@section('title', $member->name)

@section('content')
<x-sf.page-header
    title="{{ $member->name }}"
    subtitle="{{ $member->phone }}"
    back="{{ route('settings.members.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <x-sf.stat label="Saldo Poin" value="{{ number_format((float) $member->points_balance, 0, ',', '.') }}" />

    <x-sf.card title="Riwayat Poin">
        @if($member->pointTransactions->isEmpty())
            <p class="text-sm text-gray-400 py-8 text-center">Belum ada riwayat poin.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($member->pointTransactions as $tx)
                    <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div class="min-w-0">
                            <p class="text-gray-700">
                                {{ $tx->type === \App\Modules\Core\Models\MemberPointTransaction::TYPE_EARN ? 'Dapat poin' : 'Tukar poin' }}
                                @if($tx->order) &middot; {{ $tx->order->order_number }} @endif
                            </p>
                            <p class="text-xs text-gray-400">{{ $tx->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="font-semibold shrink-0 {{ (float) $tx->points >= 0 ? 'text-primary-700' : 'text-red-600' }}">
                            {{ (float) $tx->points >= 0 ? '+' : '' }}{{ number_format((float) $tx->points, 0, ',', '.') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
