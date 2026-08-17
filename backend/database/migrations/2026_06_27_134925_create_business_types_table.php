<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-managed catalog of business types (Retail Shop, Pharmacy,
     * Restaurant, ...). `website_template` and the `default_*_limit`
     * columns are intentionally unenforced metadata today — there is no
     * Website Engine yet (Sprint 8), and per-plan limits on
     * subscription_plans remain the single enforced ceiling (see
     * SubscriptionLimitService). These are defaults/suggestions surfaced
     * in the UI, not a second enforcement system.
     */
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();

            $table->string('default_currency', 3)->nullable();
            $table->decimal('default_tax_rate', 5, 2)->nullable();
            $table->json('default_units')->nullable();
            $table->string('website_template')->nullable();

            $table->boolean('inventory_enabled')->default(true);
            $table->boolean('pos_enabled')->default(false);
            $table->boolean('accounting_enabled')->default(false);
            $table->boolean('crm_enabled')->default(false);
            $table->boolean('website_enabled')->default(false);
            $table->boolean('online_ordering_enabled')->default(false);
            $table->boolean('offline_mode_enabled')->default(false);
            $table->boolean('desktop_edition_enabled')->default(false);

            $table->unsignedInteger('default_employee_limit')->nullable();
            $table->unsignedInteger('default_branch_limit')->nullable();
            $table->unsignedInteger('default_warehouse_limit')->nullable();
            $table->unsignedInteger('default_storage_limit_mb')->nullable();

            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};
