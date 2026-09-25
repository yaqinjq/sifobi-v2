<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDepartmentCategory extends Model
{
    protected $guarded = [];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }
}
