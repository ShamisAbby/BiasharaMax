<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform-wide feature registry. Rows for modules that aren't
     * actually built yet (e.g. POS, CRM, AI Assistant) are honest
     * metadata only — enabling/disabling them has no real effect until
     * that module exists. Only Inventory, Subscriptions, Licenses, Audit
     * Logs, Business/Branches/Warehouses and Employees/RBAC are real
     * today.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->string('icon')->nullable();
            $table->string('category', 60)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('visibility', 20)->default('public');
            $table->boolean('is_desktop_supported')->default(false);
            $table->boolean('is_cloud_supported')->default(true);
            $table->boolean('is_hybrid_supported')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
