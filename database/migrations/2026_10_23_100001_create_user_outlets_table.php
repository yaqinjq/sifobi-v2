<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_outlets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();

            $table->foreign('user_id', 'fk_user_outlets_user')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('outlet_id', 'fk_user_outlets_outlet')->references('id')->on('outlets')->cascadeOnDelete();
            $table->unique(['user_id', 'outlet_id'], 'uq_user_outlets_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_outlets');
    }
};
