<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Header 1 dokumen hasil hitung fisik Inventory Simple -- murni observasi,
 * BUKAN ledger/saldo kedua. Status DRAFT -> SUBMITTED TIDAK pernah memicu
 * mutasi stock_balances/stock_mutations atau memanggil service posting
 * existing apa pun -- lihat InventoryCountService.
 */
class InventoryCountDocument extends Model
{
    use LogsActivity;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    protected $table = 'inventory_count_documents';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'template_id',
        'business_date',
        'status',
        'version',
        'created_by',
        'submitted_by',
        'submitted_at',
        'last_saved_by',
        'last_saved_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('operations')
            ->logOnly(['status', 'outlet_id', 'department_id', 'template_id', 'business_date', 'version'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'submitted_at' => 'datetime',
            'last_saved_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InventoryCountTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class, 'document_id')->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(InventoryCountDocumentRevision::class, 'document_id')->latest('performed_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    protected function statusBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::STATUS_DRAFT => 'badge-draft',
            self::STATUS_SUBMITTED => 'badge-pending',
        ][$this->status] ?? 'badge-draft');
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): string => [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Terkirim',
        ][$this->status] ?? $this->status);
    }
}
