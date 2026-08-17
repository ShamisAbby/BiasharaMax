<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only activation/usage history for a license — the "Activation
     * History" / "License Usage History" feature.
     */
    public function up(): void
    {
        Schema::create('license_activation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('license_id');
            $table->uuid('license_device_id')->nullable();
            $table->string('action', 20);
            $table->string('result', 20);
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
            $table->foreign('license_device_id')->references('id')->on('license_devices')->nullOnDelete();
            $table->index(['license_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activation_logs');
    }
};
