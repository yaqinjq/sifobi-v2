<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    /**
     * User yang berhak approve untuk satu permission tertentu, dibatasi ke
     * department/outlet target kalau user tsb memang terikat department/
     * outlet (mengikuti pola scoping yang sudah dipakai di
     * PurchaseOrderController::index()) — user tanpa department_id/outlet_id
     * (Admin/Manager) otomatis dianggap bisa handle semua.
     *
     * @return Collection<int, User>
     */
    public function usersForApproval(
        int $tenantId,
        string $permission,
        ?int $departmentId = null,
        ?int $outletId = null,
    ): Collection {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->permission($permission)
            ->when($departmentId, fn (Builder $q) => $q->where(function (Builder $q) use ($departmentId): void {
                $q->whereNull('department_id')->orWhere('department_id', $departmentId);
            }))
            ->when($outletId, fn (Builder $q) => $q->where(function (Builder $q) use ($outletId): void {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
            }))
            ->get();
    }
}
