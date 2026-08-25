<?php

namespace App\Http\Controllers;

use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosTable;
use App\Modules\Production\Models\Menu;
use App\Services\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function __construct(private readonly PosOrderService $service) {}

    public function show(PosTable $table): View
    {
        $order = $table->activeOrder();

        if (! $order && $table->status !== PosTable::STATUS_AVAILABLE) {
            return view('public.table-unavailable', compact('table'));
        }

        if (! $order) {
            try {
                $order = $this->service->openOrder([
                    'outlet_id' => $table->outlet_id,
                    'order_type' => PosOrder::TYPE_DINE_IN,
                    'pos_table_id' => $table->id,
                ], (int) $table->tenant_id);
            } catch (ValidationException) {
                return view('public.table-unavailable', compact('table'));
            }
        }

        $order->load('items.menu');

        $menus = Menu::query()
            ->where('tenant_id', $table->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('selling_price')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'selling_price']);

        $addItemUrl = URL::signedRoute('public.pos.items.store', ['table' => $table->id]);

        return view('public.order', compact('table', 'order', 'menus', 'addItemUrl'));
    }

    public function addItem(Request $request, PosTable $table): RedirectResponse
    {
        $order = $table->activeOrder();

        abort_unless($order, 404);

        $validated = $request->validate([
            'menu_id' => ['required', 'integer', Rule::exists('menus', 'id')->where('tenant_id', $table->tenant_id)],
            'qty'     => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $this->service->addItem($order, $validated);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return $this->backToShow($table, 'Pesanan ditambahkan.');
    }

    public function removeItem(PosTable $table, PosOrderItem $item): RedirectResponse
    {
        $order = $table->activeOrder();

        abort_unless($order, 404);
        abort_unless((int) $item->pos_order_id === (int) $order->id, 404);

        try {
            $this->service->removeItem($order, $item);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return $this->backToShow($table, 'Item dihapus.');
    }

    /**
     * `public.pos.show` dilindungi middleware `signed` -- redirect ke situ
     * HARUS bawa signature valid sendiri, jadi tidak bisa pakai helper
     * redirect()->route() biasa (itu tidak otomatis nambahin signature).
     */
    private function backToShow(PosTable $table, string $message): RedirectResponse
    {
        $url = URL::signedRoute('public.pos.show', ['table' => $table->id]);

        return redirect()->to($url)->with('success', $message);
    }
}
