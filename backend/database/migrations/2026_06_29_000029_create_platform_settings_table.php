<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One reusable key-value settings store rather than a one-row config
     * table per feature — used by Security (password policy, lockout
     * threshold), Audit Logs (retention days), Notifications (default
     * sender), Monitoring (snapshot interval), etc.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
