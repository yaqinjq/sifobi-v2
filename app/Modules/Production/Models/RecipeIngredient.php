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
            } else {
                // Tidak ada jalur konversi sama sekali ke satuan dasar item —
                // diam-diam pakai faktor 1:1 di bawah ini SALAH kalau satuan
                // yang dipilih beda skala (mis. kg vs gr, faktor harusnya
                // 1000). RecipeController::assertResolvableUnits() mencegah
                // resep BARU tersimpan dengan kondisi ini; log ini jaring
                // pengaman untuk data resep lama yang lolos sebelum fix itu
                // ada, supaya ketahuan lewat log alih-alih diam-diam salah
                // hitung HPP/potong stok POS.
                \Illuminate\Support\Facades\Log::warning('[RECIPE] toBaseQty fallback ke faktor 1:1 — kemungkinan salah hitung', [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit_id' => $unitId,
                    'base_unit_id' => $item->base_unit_id,
                    'recipe_ingredient_id' => $this->id,
                ]);
            }
        }

        return bcmul($qty, $factor, 6);
    }
}
