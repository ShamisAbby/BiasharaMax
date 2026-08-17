<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only — "Update History". Mirrors the audit_logs /
     * license_activation_logs shape (no updated_at).
     */
    public function up(): void
    {
        Schema::create('module_version_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('from_version', 20)->nullable();
            $table->string('to_version', 20);
            $table->uuid('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['module_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_version_history');
    }
};
