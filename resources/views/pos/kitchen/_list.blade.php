@if($orders->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-3xl mb-4">🍽️</div>
        <h3 class="font-heading font-semibold text-gray-900 text-base">Tidak ada order menunggu</h3>
        <p class="text-sm text-gray-500 mt-2">Semua item sudah diproses.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($orders as $order)
            <div class="sf-card p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $order->order_number }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $order->table ? 'Meja '.$order->table->code : 'Takeaway' }}
                            &middot; {{ $order->opened_at?->diffForHumans() }}
                        </p>
                    </div>
                    <span class="{{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span>
                </div>
                <div class="divide-y divide-gray-50 border-t border-gray-100">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ (float) $item->qty }}x {{ $item->item_name }}</p>
                                @if($item->notes)
                                    <p class="text-xs text-gray-400 truncate">{{ $item->notes }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('pos.kitchen.items.ready', $item) }}">
                                @csrf
                                <button type="submit" class="sf-btn-primary text-xs px-3 py-1.5 shrink-0">Tandai Siap</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
