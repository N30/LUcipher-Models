<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('lu::models.audit.table', 'lu_models_audit'), function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('model_id', 64)->nullable();
            $table->string('action', 16);
            $table->string('transport', 16)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at');

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('lu::models.audit.table', 'lu_models_audit'));
    }
};
