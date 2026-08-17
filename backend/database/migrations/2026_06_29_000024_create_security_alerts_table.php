<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Covers "Suspicious Activities", "Permission
     * Violations" and the "Security Timeline" — one generic table with
     * a `type` discriminator rather than a table per alert kind.
     */
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->string('severity', 10)->default('low');
            $table->string('subject_type', 20)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('resolved_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['severity', 'is_resolved']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
