<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The real per-tenant module activation record — "Assign to
     * Businesses" / Install / Uninstall. Richer than a plain pivot since
     * it tracks its own enabled state and install/uninstall timestamps.
     */
    public function up(): void
    {
        Schema::create('business_module', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('module_id');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'module_id']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_module');
    }
};
