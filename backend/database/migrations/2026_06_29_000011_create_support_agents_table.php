<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An agent is an existing PlatformUser plus an agent profile row —
     * not a separate identity/auth system.
     */
    public function up(): void
    {
        Schema::create('support_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('platform_user_id')->unique();
            $table->uuid('support_department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('max_concurrent_tickets')->nullable();
            $table->timestamps();

            $table->foreign('platform_user_id')->references('id')->on('platform_users')->cascadeOnDelete();
            $table->foreign('support_department_id')->references('id')->on('support_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_agents');
    }
};
