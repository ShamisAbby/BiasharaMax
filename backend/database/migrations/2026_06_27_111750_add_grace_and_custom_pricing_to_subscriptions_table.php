<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Lapsed subscriptions keep access until this timestamp (7-day
            // grace period) before the business is actually locked out.
            $table->timestamp('grace_period_ends_at')->nullable()->after('canceled_at');

            // Negotiated/enterprise deals: overrides the catalog plan's
            // price and limits for this one business only.
            $table->decimal('custom_price', 12, 2)->nullable()->after('grace_period_ends_at');
            $table->json('custom_limits')->nullable()->after('custom_price');

            $table->boolean('auto_renew')->default(false)->after('custom_limits');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['grace_period_ends_at', 'custom_price', 'custom_limits', 'auto_renew']);
        });
    }
};
