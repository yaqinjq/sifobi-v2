<?php

namespace App\Modules\Core\Models;

use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $guarded = [];

    public function primaryItems(): HasMany
    {
        return $this->hasMany(Item::class, 'primary_department_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Item::class,
            'item_departments',
            'department_id',
            'item_id'
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_operational' => 'boolean',
        ];
    }
}
