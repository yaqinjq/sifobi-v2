<?php

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitConversion;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'buy_qty'    => 'decimal:6',
            'buy_price'  => 'decimal:4',
            'recipe_qty' => 'decimal:6',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function buyUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'buy_unit_id');
    }

    public function recipeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'recipe_unit_id');
    }

    /**
     * Biaya baris ini = (pemakaian resep dikonversi ke satuan dasar item /
     * qty pembelian dikonversi ke satuan dasar item) * harga beli manual.
     * Konversi meniru GoodsReceiptService::calculateBaseQty() supaya
     * konsisten dengan cara SIFOBI menghitung satuan di tempat lain.
     */
    public function cost(): string
    {
        if (bccomp((string) $this->buy_qty, '0', 6) <= 0) {
            return '0.0000';
        }

        $item = $this->relationLoaded('item') ? $this->item : $this->item()->first();

        if (! $item) {
            return '0.0000';
        }

        $recipeQtyBase = $this->recipeQtyBase();
        $buyQtyBase = $this->buyQtyBase();

        if (bccomp($buyQtyBase, '0', 6) <= 0) {
            return '0.0000';
        }

        return bcmul(bcdiv($recipeQtyBase, $buyQtyBase, 6), (string) $this->buy_price, 4);
    }

    public function recipeQtyBase(): string
    {
        $item = $this->relationLoaded('item') ? $this->item : $this->item()->first();

        return $item ? $this->toBaseQty($item, (int) $this->recipe_unit_id, (string) $this->recipe_qty) : '0.000000';
    }

    public function buyQtyBase(): string
    {
        $item = $this->relationLoaded('item') ? $this->item : $this->item()->first();

        return $item ? $this->toBaseQty($item, (int) $this->buy_unit_id, (string) $this->buy_qty) : '0.000000';
    }

    private function toBaseQty(Item $item, int $unitId, string $qty): string
    {
        $factor = '1.000000';

        if ((int) $item->base_unit_id === $unitId) {
            $factor = '1.000000';
        } elseif ((int) $item->inventory_unit_id === $unitId && $item->inventory_ratio) {
            $factor = Decimal::toFixed($item->inventory_ratio, 6);
        } elseif ((int) $item->purchase_unit_id === $unitId && $item->purchase_ratio) {
            $factor = Decimal::toFixed($item->purchase_ratio, 6);
        } else {
            $conversion = UnitConversion::withoutGlobalScopes()
                ->where('tenant_id', $item->tenant_id)
                ->where('item_id', $item->id)
                ->where('from_unit_id', $unitId)
                ->where('to_unit_id', $item->base_unit_id)
                ->first();

            if ($conversion) {
                $factor = Decimal::toFixed($conversion->factor, 6);
            }
        }

        return bcmul($qty, $factor, 6);
    }
}
