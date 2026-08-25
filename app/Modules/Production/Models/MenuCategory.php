<?php

namespace App\Modules\Production\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MenuCategory extends Model
{
    use LogsActivity;

    public const COLORS = [
        'gray',
        'green',
        'blue',
        'amber',
        'red',
        'purple',
        'rose',
        'indigo',
    ];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function badgeClass(): string
    {
        return match ($this->color) {
            'green' => 'badge-active',
            'blue' => 'badge-blue',
            'amber' => 'badge-pending',
            'red' => 'badge-rejected',
            'purple' => 'badge-purple',
            'rose' => 'badge-rose',
            'indigo' => 'badge-indigo',
            default => 'badge-draft',
        };
    }

    /**
     * Kelas warna latar polos (bukan pill badge) buat placeholder foto
     * menu yang belum punya foto -- static map juga (C1.5), dipakai
     * kartu katalog kasir supaya kotak placeholder-nya ikut warna
     * kategori, bukan abu-abu semua.
     */
    public function placeholderClass(): string
    {
        return match ($this->color) {
            'green' => 'ph-green',
            'blue' => 'ph-blue',
            'amber' => 'ph-amber',
            'red' => 'ph-red',
            'purple' => 'ph-purple',
            'rose' => 'ph-rose',
            'indigo' => 'ph-indigo',
            default => 'ph-gray',
        };
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('production')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
