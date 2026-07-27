<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: merge duplicate category names within each tenant
        // Keep the category that has the most items; tie-break by lowest id (oldest).
        $groups = DB::table('item_categories')
            ->select('tenant_id', DB::raw('LOWER(name) as name_lower'))
            ->groupBy('tenant_id', DB::raw('LOWER(name)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('item_categories')
                ->where('tenant_id', $group->tenant_id)
                ->whereRaw('LOWER(name) = ?', [$group->name_lower])
                ->orderBy('id')
                ->pluck('id');

            // Pick winner: most items, then lowest id
            $keepId = DB::table('item_categories as ic')
                ->leftJoin('items as i', 'i.item_category_id', '=', 'ic.id')
                ->whereIn('ic.id', $ids)
                ->groupBy('ic.id')
                ->orderByRaw('COUNT(i.id) DESC')
                ->orderBy('ic.id')
                ->value('ic.id');

            $removeIds = $ids->filter(fn ($id) => $id !== $keepId)->values();

            if ($removeIds->isEmpty()) {
                continue;
            }

            // Reassign items to the winner
            DB::table('items')
                ->whereIn('item_category_id', $removeIds)
                ->update(['item_category_id' => $keepId]);

            // Delete the duplicates
            DB::table('item_categories')
                ->whereIn('id', $removeIds)
                ->delete();
        }

        // Step 2: add unique constraint on (tenant_id, name) to prevent future duplicates
        if (! $this->uniqueExists('item_categories', 'uq_item_categories_tenant_name')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'name'], 'uq_item_categories_tenant_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table): void {
            $table->dropUnique('uq_item_categories_tenant_name');
        });
    }

    private function uniqueExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
