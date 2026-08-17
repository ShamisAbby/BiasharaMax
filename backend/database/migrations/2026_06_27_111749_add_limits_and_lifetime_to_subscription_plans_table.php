<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_lifetime', 12, 2)->nullable()->after('price_yearly');
            $table->string('type', 20)->default('standard')->after('slug');

            // Nullable limit columns mean "unlimited" for that resource.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_warehouses')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_employees')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_api_requests_per_day')->nullable();
            $table->unsignedInteger('max_notifications_per_month')->nullable();

            $table->boolean('includes_website')->default(false);
            $table->boolean('includes_ai')->default(false);
            $table->boolean('includes_offline_sync')->default(false);
            $table->boolean('includes_desktop_edition')->default(false);
            $table->boolean('includes_reports')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'price_lifetime',
                'type',
                'max_users',
                'max_branches',
                'max_warehouses',
                'max_products',
                'max_employees',
                'max_storage_mb',
                'max_api_requests_per_day',
                'max_notifications_per_month',
                'includes_website',
                'includes_ai',
                'includes_offline_sync',
                'includes_desktop_edition',
                'includes_reports',
            ]);
        });
    }
};
