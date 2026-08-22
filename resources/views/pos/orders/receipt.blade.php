<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; background: #f3f4f6; color: #111827; padding: 2rem 0; }
        .receipt { width: 320px; margin: 0 auto; background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        h1 { font-size: 1rem; text-align: center; margin-bottom: 0.25rem; }
        .sub { text-align: center; font-size: 0.75rem; color: #6b7280; margin-bottom: 1rem; }
        .divider { border-top: 1px dashed #9ca3af; margin: 0.75rem 0; }
        .row { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.15rem; }
        .item-name { font-size: 0.8rem; }
        .item-detail { font-size: 0.75rem; color: #6b7280; }
        .total-row { font-weight: bold; font-size: 0.9rem; }
        .actions { width: 320px; margin: 1rem auto 0; text-align: center; }
        .btn { display: inline-block; background: #1B4332; color: #fff; padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-family: system-ui, sans-serif; font-size: 0.875rem; border: none; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; width: 100%; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <h1>{{ $order->outlet->name ?? config('app.name') }}</h1>
        <p class="sub">{{ $order->order_number }}</p>
        <p class="sub">
            {{ $order->order_type === \App\Modules\Pos\Models\PosOrder::TYPE_DINE_IN ? 'Dine-in' : 'Takeaway' }}
            @if($order->table) &middot; Meja {{ $order->table->code }} @endif
            &middot; {{ optional($order->closed_at)->format('d/m/Y H:i') }}
        </p>
        <div class="divider"></div>

        @foreach($order->items as $item)
            <div class="item-name">{{ $item->item_name }}</div>
            <div class="row item-detail">
                <span>{{ (float) $item->qty }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}</span>
                <span>{{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="row"><span>Subtotal</span><span>{{ number_format((float) $order->subtotal, 0, ',', '.') }}</span></div>
        <div class="row"><span>Diskon</span><span>-{{ number_format((float) $order->discount_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Pajak</span><span>{{ number_format((float) $order->tax_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Service</span><span>{{ number_format((float) $order->service_charge_amount, 0, ',', '.') }}</span></div>
        <div class="divider"></div>
        <div class="row total-row"><span>TOTAL</span><span>Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span></div>

        <div class="divider"></div>
        @foreach($order->payments as $payment)
            <div class="row"><span>{{ $payment->methodLabel() }}</span><span>{{ number_format((float) $payment->amount, 0, ',', '.') }}</span></div>
        @endforeach

        <p class="sub" style="margin-top:1rem;">Terima kasih telah berkunjung</p>
    </div>

    <div class="actions">
        <button class="btn" onclick="window.print()">Cetak Struk</button>
    </div>
</body>
</html>
