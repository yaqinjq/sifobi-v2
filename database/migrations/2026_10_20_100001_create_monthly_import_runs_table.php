<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_import_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('type', 20); // OPNAME | SPOIL
            $table->json('scope'); // outlet_id, department_id, month
            $table->string('status', 20)->default('QUEUED'); // QUEUED | PROCESSING | DONE | FAILED
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('processed_units')->default(0);
            $table->unsignedInteger('failed_units')->default(0);
            $table->json('errors')->nullable();
            $table->string('upload_path');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_import_runs');
    }
};
