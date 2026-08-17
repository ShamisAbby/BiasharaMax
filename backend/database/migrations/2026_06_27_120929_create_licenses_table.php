<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desktop Edition licensing. BiasharaMax has no desktop client yet
     * (see docs: Desktop Edition is Sprint 11, built on Electron/Tauri) —
     * this table and its server-side activation/validation logic are
     * real and complete; what's deferred is the client that would
     * capture a hardware fingerprint and call these endpoints.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('license_key', 29)->unique();
            $table->string('type', 20);
            $table->unsignedInteger('max_devices')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('maintenance_expires_at')->nullable();
            $table->boolean('offline_activation_allowed')->default(true);
            $table->boolean('cloud_sync_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
