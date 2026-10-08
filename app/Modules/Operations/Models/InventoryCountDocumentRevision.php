<?php

namespace App\Modules\Operations\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit tiap perubahan status dokumen Inventory Simple (terutama
 * SUBMITTED -> DRAFT lewat requestRevision()) -- supaya koreksi punya
 * alasan tercatat, bukan overwrite diam-diam atau delete histori.
 */
class InventoryCountDocumentRevision extends Model
{
    protected $table = 'inventory_count_document_revisions';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'from_status',
        'to_status',
        'reason',
        'performed_by',
        'performed_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryCountDocument::class, 'document_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
