<?php

namespace App\Services;

use App\Modules\Production\Models\Recipe;
use App\Modules\Production\Models\RecipeApprovalEvent;
use App\Modules\Production\Models\RecipeOtherCost;
use App\Modules\Production\Models\RecipeOutlet;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RecipeService
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipients = new NotificationRecipientResolver(),
    ) {}

    /**
     * @param  array<string, mixed>  $data  tenant_id, menu_id, test_date, witnessed_by_user_ids,
     *                                       witnessed_by_names, food_panel_user_ids, food_panel_names,
     *                                       volume_production, notes
     * @param  array<int, array<string, mixed>>  $ingredients  item_id, buy_qty, buy_unit_id, buy_price, recipe_qty, recipe_unit_id
     * @param  array<int, array<string, mixed>>  $otherCosts  cost_type, label, amount
     */
    public function createDraft(array $data, array $ingredients, array $otherCosts, int $userId): Recipe
    {
        return DB::transaction(function () use ($data, $ingredients, $otherCosts, $userId): Recipe {
            $tenantId = (int) $data['tenant_id'];
            $menuId = (int) $data['menu_id'];

            $recipe = Recipe::query()->create([
                'tenant_id'              => $tenantId,
                'menu_id'                => $menuId,
                'version_number'         => Recipe::nextVersionNumber($menuId),
                'status'                 => Recipe::STATUS_DRAFT,
                'created_by'             => $userId,
                'test_date'              => $data['test_date'] ?? null,
                'witnessed_by_user_ids'  => $data['witnessed_by_user_ids'] ?? null,
                'witnessed_by_names'     => $data['witnessed_by_names'] ?? null,
                'food_panel_user_ids'    => $data['food_panel_user_ids'] ?? null,
                'food_panel_names'       => $data['food_panel_names'] ?? null,
                'volume_production'      => $data['volume_production'] ?? 1,
                'notes'                  => $data['notes'] ?? null,
            ]);

            $this->syncIngredients($recipe, $ingredients);
            $this->syncOtherCosts($recipe, $otherCosts);

            return $recipe->fresh(['ingredients.item', 'otherCosts']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $ingredients
     * @param  array<int, array<string, mixed>>  $otherCosts
     */
    public function updateDraft(Recipe $recipe, array $data, array $ingredients, array $otherCosts): Recipe
    {
        return DB::transaction(function () use ($recipe, $data, $ingredients, $otherCosts): Recipe {
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipe->id);

            if (! $recipe->canEdit()) {
                throw ValidationException::withMessages(['status' => 'Resep hanya bisa diedit saat masih draft.']);
            }

            $recipe->update([
                'test_date'              => $data['test_date'] ?? null,
                'witnessed_by_user_ids'  => $data['witnessed_by_user_ids'] ?? null,
                'witnessed_by_names'     => $data['witnessed_by_names'] ?? null,
                'food_panel_user_ids'    => $data['food_panel_user_ids'] ?? null,
                'food_panel_names'       => $data['food_panel_names'] ?? null,
                'volume_production'      => $data['volume_production'] ?? 1,
                'notes'                  => $data['notes'] ?? null,
            ]);

            $recipe->ingredients()->delete();
            $recipe->otherCosts()->delete();
            $this->syncIngredients($recipe, $ingredients);
            $this->syncOtherCosts($recipe, $otherCosts);

            return $recipe->fresh(['ingredients.item', 'otherCosts']);
        });
    }

    public function submit(Recipe $recipe, int $userId): Recipe
    {
        $recipe = DB::transaction(function () use ($recipe, $userId): Recipe {
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipe->id);

            if (! $recipe->canSubmit()) {
                throw ValidationException::withMessages(['status' => 'Hanya resep draft yang bisa diajukan.']);
            }

            if (! $recipe->ingredients()->exists()) {
                throw ValidationException::withMessages(['ingredients' => 'Resep harus memiliki minimal 1 bahan sebelum diajukan.']);
            }

            $oldStatus = $recipe->status;

            $recipe->update(['status' => Recipe::STATUS_SUBMITTED]);

            $this->logEvent($recipe, $userId, RecipeApprovalEvent::EVENT_SUBMIT, $oldStatus, Recipe::STATUS_SUBMITTED);

            return $recipe->refresh();
        });

        // Resep tidak terikat department/outlet — approver-nya tenant-wide.
        $approvers = $this->recipients->usersForApproval((int) $recipe->tenant_id, 'approve_recipes');
        Notification::send($approvers, new WorkflowNotification(
            "Resep {$recipe->menu?->name} v{$recipe->version_number} perlu persetujuan",
            "Resep {$recipe->menu?->name} versi {$recipe->version_number} diajukan dan menunggu persetujuan Anda.",
            route('production.recipes.show', $recipe),
            'production',
        ));

        return $recipe;
    }

    /**
     * @param  array<int, int>  $outletIds
     */
    public function approve(Recipe $recipe, int $userId, array $outletIds): Recipe
    {
        $recipe = DB::transaction(function () use ($recipe, $userId, $outletIds): Recipe {
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipe->id);

            if (! $recipe->canApprove()) {
                throw ValidationException::withMessages(['status' => 'Hanya resep yang diajukan yang bisa disetujui.']);
            }

            if (empty($outletIds)) {
                throw ValidationException::withMessages(['outlet_ids' => 'Pilih minimal 1 outlet untuk menerapkan resep ini.']);
            }

            $oldStatus = $recipe->status;

            $recipe->update([
                'status'      => Recipe::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // 1 outlet cuma boleh punya 1 resep aktif per menu — lepas
            // assignment versi lama milik menu yang sama di outlet-outlet ini.
            RecipeOutlet::withoutGlobalScopes()
                ->whereIn('outlet_id', $outletIds)
                ->whereHas('recipe', fn ($q) => $q->where('menu_id', $recipe->menu_id))
                ->delete();

            foreach ($outletIds as $outletId) {
                RecipeOutlet::query()->create([
                    'tenant_id'    => $recipe->tenant_id,
                    'recipe_id'    => $recipe->id,
                    'outlet_id'    => $outletId,
                    'assigned_by'  => $userId,
                    'assigned_at'  => now(),
                ]);
            }

            $this->logEvent($recipe, $userId, RecipeApprovalEvent::EVENT_APPROVE, $oldStatus, Recipe::STATUS_APPROVED);

            return $recipe->refresh();
        });

        $recipe->createdBy?->notify(new WorkflowNotification(
            "Resep {$recipe->menu?->name} v{$recipe->version_number} disetujui",
            "Resep {$recipe->menu?->name} versi {$recipe->version_number} yang Anda ajukan sudah disetujui.",
            route('production.recipes.show', $recipe),
            'production',
        ));

        return $recipe;
    }

    public function reject(Recipe $recipe, int $userId, string $reason): Recipe
    {
        $recipe = DB::transaction(function () use ($recipe, $userId, $reason): Recipe {
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipe->id);

            if (! $recipe->canReject()) {
                throw ValidationException::withMessages(['status' => 'Resep ini tidak bisa ditolak pada status saat ini.']);
            }

            $oldStatus = $recipe->status;

            $recipe->update([
                'status'           => Recipe::STATUS_REJECTED,
                'rejected_reason'  => $reason,
            ]);

            $this->logEvent($recipe, $userId, RecipeApprovalEvent::EVENT_REJECT, $oldStatus, Recipe::STATUS_REJECTED, $reason);

            return $recipe->refresh();
        });

        $recipe->createdBy?->notify(new WorkflowNotification(
            "Resep {$recipe->menu?->name} v{$recipe->version_number} ditolak",
            "Resep {$recipe->menu?->name} versi {$recipe->version_number} ditolak. Alasan: {$reason}",
            route('production.recipes.show', $recipe),
            'production',
        ));

        return $recipe;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ingredients
     */
    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        foreach (array_values($ingredients) as $index => $row) {
            if (empty($row['item_id'])) {
                continue;
            }

            $recipe->ingredients()->create([
                'tenant_id'      => $recipe->tenant_id,
                'item_id'        => $row['item_id'],
                'buy_qty'        => $row['buy_qty'] ?? 0,
                'buy_unit_id'    => $row['buy_unit_id'],
                'buy_price'      => $row['buy_price'] ?? 0,
                'recipe_qty'     => $row['recipe_qty'] ?? 0,
                'recipe_unit_id' => $row['recipe_unit_id'],
                'sort_order'     => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $otherCosts
     */
    private function syncOtherCosts(Recipe $recipe, array $otherCosts): void
    {
        foreach (array_values($otherCosts) as $index => $row) {
            if (empty($row['label']) && empty($row['amount'])) {
                continue;
            }

            $recipe->otherCosts()->create([
                'tenant_id'  => $recipe->tenant_id,
                'cost_type'  => $row['cost_type'] ?? RecipeOtherCost::TYPE_PRODUCTION,
                'label'      => $row['label'] ?? '',
                'amount'     => $row['amount'] ?? 0,
                'sort_order' => $index,
            ]);
        }
    }

    private function logEvent(
        Recipe $recipe,
        int $userId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        ?string $notes = null
    ): void {
        RecipeApprovalEvent::query()->create([
            'tenant_id'  => $recipe->tenant_id,
            'recipe_id'  => $recipe->id,
            'actor_id'   => $userId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status'  => $toStatus,
            'notes'      => $notes,
        ]);
    }
}
