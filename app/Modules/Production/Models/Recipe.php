<?php

namespace App\Modules\Production\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Recipe extends Model
{
    use LogsActivity;

    protected $guarded = [];

    const STATUS_DRAFT     = 'DRAFT';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_APPROVED  = 'APPROVED';
    const STATUS_REJECTED  = 'REJECTED';

    const STATUS_LABELS = [
        self::STATUS_DRAFT     => 'Draft',
        self::STATUS_SUBMITTED => 'Diajukan',
        self::STATUS_APPROVED  => 'Disetujui',
        self::STATUS_REJECTED  => 'Ditolak',
    ];

    protected function casts(): array
    {
        return [
            'test_date'              => 'date',
            'witnessed_by_user_ids'  => 'array',
            'food_panel_user_ids'    => 'array',
            'volume_production'      => 'decimal:4',
            'approved_at'            => 'datetime',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function otherCosts(): HasMany
    {
        return $this->hasMany(RecipeOtherCost::class)->orderBy('sort_order');
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'recipe_outlets')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function approvalEvents(): HasMany
    {
        return $this->hasMany(RecipeApprovalEvent::class)->orderBy('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
            self::STATUS_APPROVED  => 'badge-active',
            self::STATUS_REJECTED  => 'badge-void',
            default                => 'badge-draft',
        };
    }

    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canReject(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canDelete(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Breakdown HPP: per-bahan, total bahan, total biaya lain, total, dan
     * HPP per unit (total / volume_production). Butuh relasi `ingredients`
     * dan `otherCosts` sudah di-load (atau akan di-lazy-load di sini).
     *
     * @return array<string, mixed>
     */
    public function hpp(): array
    {
        $ingredients = $this->relationLoaded('ingredients') ? $this->ingredients : $this->ingredients()->with('item.baseUnit', 'buyUnit', 'recipeUnit')->get();
        $otherCosts = $this->relationLoaded('otherCosts') ? $this->otherCosts : $this->otherCosts()->get();

        $ingredientRows = [];
        $ingredientsTotal = '0.0000';

        foreach ($ingredients as $ingredient) {
            $cost = $ingredient->cost();
            $ingredientsTotal = bcadd($ingredientsTotal, $cost, 4);
            $ingredientRows[] = [
                'ingredient' => $ingredient,
                'cost'       => $cost,
            ];
        }

        $otherCostRows = [];
        $otherCostsTotal = '0.0000';

        foreach ($otherCosts as $otherCost) {
            $otherCostsTotal = bcadd($otherCostsTotal, (string) $otherCost->amount, 4);
            $otherCostRows[] = $otherCost;
        }

        $totalCost = bcadd($ingredientsTotal, $otherCostsTotal, 4);
        $volume = bccomp((string) $this->volume_production, '0', 4) > 0 ? (string) $this->volume_production : '1.0000';
        $hppPerUnit = bcdiv($totalCost, $volume, 4);

        return [
            'ingredient_rows'   => $ingredientRows,
            'other_cost_rows'   => $otherCostRows,
            'ingredients_total' => $ingredientsTotal,
            'other_costs_total' => $otherCostsTotal,
            'total_cost'        => $totalCost,
            'volume_production' => $volume,
            'hpp_per_unit'      => $hppPerUnit,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('production')
            ->logOnly(['status', 'menu_id', 'version_number', 'test_date', 'volume_production', 'approved_by', 'rejected_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public static function nextVersionNumber(int $menuId): int
    {
        $max = static::withoutGlobalScopes()->where('menu_id', $menuId)->max('version_number');

        return ((int) $max) + 1;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
