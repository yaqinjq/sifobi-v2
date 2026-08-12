<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('item_source', 50)->nullable()->after('po_destinations');
            $table->index(['tenant_id', 'item_source'], 'items_tenant_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_tenant_source_idx');
            $table->dropColumn('item_source');
        });
    }
};
